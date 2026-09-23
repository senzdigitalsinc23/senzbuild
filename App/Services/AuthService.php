<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\DTOs\AuthResponseDTO;
use App\DTOs\LoginRequestDTO;
use App\DTOs\RegisterRequestDTO;
use App\DTOs\UserDTO;
use App\Exceptions\AuthException;
use App\Models\RefreshToken;
use App\Repositories\AuthUserRepository;
use App\Repositories\RefreshTokenRepository;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PDO;

/**
 * Authentication service.
 *
 * Handles login, registration, token generation, refresh token rotation, and validation.
 * Uses AuthUserRepository and RefreshTokenRepository for all persistence
 * and returns DTOs — no raw arrays leak out of this layer.
 */
class AuthService
{
    private string             $secret;
    private int                $ttl;
    private int                $refreshTtl;
    private AuthUserRepository $repo;
    private RefreshTokenRepository $refreshRepo;

    public function __construct(?AuthUserRepository $repo = null, ?RefreshTokenRepository $refreshRepo = null)
    {
        $this->secret     = $_ENV['JWT_SECRET'] ?? 'change_me_in_env';
        $this->ttl        = (int)($_ENV['JWT_TTL'] ?? 86400);          // 24 h access token
        $this->refreshTtl = (int)($_ENV['JWT_REFRESH_TTL'] ?? 2592000); // 30 d refresh token
        $this->repo       = $repo       ?? new AuthUserRepository();
        $this->refreshRepo = $refreshRepo ?? new RefreshTokenRepository();
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Authenticate a user and return an AuthResponseDTO with access + refresh tokens.
     *
     * @throws AuthException on invalid credentials, inactive account, or locked account
     */
    public function login(LoginRequestDTO $dto): AuthResponseDTO
    {
        $user = $this->repo->findByEmail($dto->email);

        if (!$user || !password_verify($dto->password, $user->password)) {
            throw AuthException::invalidCredentials();
        }

        if (!$user->isActive) {
            throw AuthException::accountInactive();
        }

        $this->repo->updateLastLogin($user->id);

        $requires2fa = $this->check2faEnabled($user->id);

        return new AuthResponseDTO(
            user:        $user,
            token:       $this->generateAccessToken($user),
            refreshToken: $this->generateRefreshToken($user->id),
            expiresIn:   $this->ttl,
            requires2fa: $requires2fa,
        );
    }

    /**
     * Register a new user and return an AuthResponseDTO with access + refresh tokens.
     *
     * @throws AuthException if email already exists
     */
    public function register(RegisterRequestDTO $dto): AuthResponseDTO
    {
        if ($this->repo->emailExists($dto->email)) {
            throw AuthException::emailAlreadyExists($dto->email);
        }

        $user = $this->repo->create(new \App\DTOs\UserCreateDTO(
            email:    $dto->email,
            password: $dto->password,
            roleId:   (int)$dto->roleId,
        ));

        return new AuthResponseDTO(
            user:         $user,
            token:        $this->generateAccessToken($user),
            refreshToken: $this->generateRefreshToken($user->id),
            expiresIn:    $this->ttl,
        );
    }

    /**
     * Exchange a valid refresh token for a new access token + a rotated refresh token.
     * The old refresh token is revoked (rotation).
     *
     * @throws AuthException if the refresh token is invalid, expired, or revoked
     */
    public function refresh(string $refreshToken): AuthResponseDTO
    {
        $tokenModel = $this->refreshRepo->findByToken($refreshToken);

        if (!$tokenModel) {
            throw AuthException::invalidRefreshToken();
        }

        // Support both object and array returns from repository
        $userId = is_object($tokenModel)
            ? ($tokenModel->user_id ?? $tokenModel->attributes['user_id'] ?? null)
            : ($tokenModel['user_id'] ?? null);

        $user = $this->repo->findById($userId);
        if (!$user || !$user->isActive) {
            // Revoke the token so it can't be reused
            $this->refreshRepo->revokeByToken($refreshToken);
            throw AuthException::accountInactive();
        }

        // Rotation: revoke the old refresh token and issue a new pair
        $this->refreshRepo->revokeByToken($refreshToken);
        $newRefreshToken = $this->generateRefreshToken($user->id);

        return new AuthResponseDTO(
            user:         $user,
            token:        $this->generateAccessToken($user),
            refreshToken: $newRefreshToken,
            expiresIn:    $this->ttl,
        );
    }

    /**
     * Revoke the current refresh token (logout from this device).
     */
    public function logout(string $refreshToken): void
    {
        $this->refreshRepo->revokeByToken($refreshToken);
    }

    /**
     * Revoke ALL refresh tokens for the current user (logout from all devices).
     */
    public function logoutAll(string $userId): int
    {
        return $this->refreshRepo->revokeAllForUser($userId);
    }

    /**
     * Validate a JWT and return the corresponding UserDTO.
     * Returns null (does NOT throw) so middleware can decide the response.
     */
    public function validateToken(string $token): ?UserDTO
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
            return $this->repo->findById((string)$decoded->sub);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Return the authenticated user from the current request's Bearer token.
     * Convenience wrapper used by the controller's /me endpoint.
     */
    public function currentUser(string $bearerToken): ?UserDTO
    {
        return $this->validateToken($bearerToken);
    }

    /**
     * List all active refresh tokens for a user (remembered devices).
     */
    public function listRefreshTokens(string $userId): array
    {
        return $this->refreshRepo->findByUser($userId);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function generateAccessToken(UserDTO $user): string
    {
        $now = time();
        return JWT::encode([
            'iss'   => $_ENV['APP_URL'] ?? 'multishop',
            'sub'   => $user->id,
            'iat'   => $now,
            'exp'   => $now + $this->ttl,
            'role'  => $user->roleId,
            'scope' => $user->storeScope,
        ], $this->secret, 'HS256');
    }

    private function generateRefreshToken(string $userId): string
    {
        $plainToken = bin2hex(random_bytes(64));
        $this->refreshRepo->create($userId, $plainToken);
        return $plainToken;
    }

    private function check2faEnabled(string $userId): bool
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT totp_enabled FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row && !empty($row['totp_enabled']);
        } catch (\Throwable) {
            // Column may not exist yet (migration not run) — default to false
            return false;
        }
    }
}
