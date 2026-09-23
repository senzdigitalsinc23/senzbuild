<?php
declare(strict_types=1);

namespace App\Core;

/**
 * OAuth2 Authorization Server — implements core OAuth2 flows.
 *
 * Supported grant types: authorization_code, client_credentials, refresh_token, password
 *
 * Usage:
 *   $server = new OAuth2Server($container);
 *   $token  = $server->issueAccessToken([
 *       'grant_type'    => 'authorization_code',
 *       'client_id'     => 'my-client',
 *       'client_secret' => 'secret',
 *       'code'          => 'auth-code',
 *       'redirect_uri'  => 'https://app.com/callback',
 *   ]);
 */
class OAuth2Server
{
    protected Container $container;
    protected int $accessTokenTtl;
    protected int $refreshTokenTtl;
    protected string $encryptionKey;

    /** @var array<string, array> In-memory stores (use DB in production) */
    protected static array $clients    = [];
    protected static array $authCodes  = [];
    protected static array $accessTokens = [];
    protected static array $refreshTokens = [];
    protected static array $users      = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
        try {
            $this->accessTokenTtl = (int)Config::get('oauth2.access_token_ttl', 3600);
            $this->refreshTokenTtl = (int)Config::get('oauth2.refresh_token_ttl', 1209600);
        } catch (\Exception $e) {
            $this->accessTokenTtl = 3600;
            $this->refreshTokenTtl = 1209600;
        }
        $this->encryptionKey = $_ENV['JWT_SECRET'] ?? bin2hex(random_bytes(32));
    }

    // ── Client Management ────────────────────────────────────────────────

    public function registerClient(string $clientId, string $clientSecret, array $redirectUris, array $scopes = []): void
    {
        self::$clients[$clientId] = [
            'client_id'     => $clientId,
            'client_secret' => password_hash($clientSecret, PASSWORD_BCRYPT),
            'redirect_uris' => $redirectUris,
            'scopes'        => $scopes ?: ['read', 'write'],
            'created_at'    => time(),
        ];
    }

    public function validateClient(string $clientId, ?string $clientSecret = null): ?array
    {
        $client = self::$clients[$clientId] ?? null;
        if ($client === null) {
            return null;
        }
        if ($clientSecret !== null && !password_verify($clientSecret, $client['client_secret'])) {
            return null;
        }
        return $client;
    }

    // ── Authorization Code Flow ──────────────────────────────────────────

    /**
     * Generate an authorization code for the authorization code flow.
     */
    public function issueAuthCode(string $clientId, string $userId, string $redirectUri, array $scopes = []): string
    {
        $code = bin2hex(random_bytes(32));
        $exp  = time() + 600; // 10 min expiry

        self::$authCodes[$code] = [
            'client_id'  => $clientId,
            'user_id'    => $userId,
            'redirect_uri' => $redirectUri,
            'scopes'     => $scopes,
            'expires_at' => $exp,
            'used'       => false,
        ];

        return $code;
    }

    /**
     * Exchange an authorization code for an access token.
     */
    public function exchangeAuthCode(string $code, string $clientId, string $clientSecret, string $redirectUri): array
    {
        $authCode = self::$authCodes[$code] ?? null;

        if ($authCode === null) {
            throw new \InvalidArgumentException('Invalid authorization code');
        }
        if ($authCode['used']) {
            throw new \InvalidArgumentException('Authorization code already used');
        }
        if ($authCode['client_id'] !== $clientId) {
            throw new \InvalidArgumentException('Client ID mismatch');
        }
        if ($authCode['redirect_uri'] !== $redirectUri) {
            throw new \InvalidArgumentException('Redirect URI mismatch');
        }
        if ($authCode['expires_at'] < time()) {
            throw new \InvalidArgumentException('Authorization code expired');
        }

        // Mark code as used
        self::$authCodes[$code]['used'] = true;

        return $this->issueAccessTokenForUser(
            $authCode['user_id'],
            $clientId,
            $authCode['scopes']
        );
    }

    // ── Client Credentials Flow ──────────────────────────────────────────

    public function issueClientCredentialsToken(string $clientId, string $clientSecret, array $scopes = []): array
    {
        $client = $this->validateClient($clientId, $clientSecret);
        if ($client === null) {
            throw new \InvalidArgumentException('Invalid client credentials');
        }

        $allowedScopes = array_intersect($scopes, $client['scopes']);

        return $this->issueAccessTokenForUser(null, $clientId, $allowedScopes);
    }

    // ── Password Grant Flow ──────────────────────────────────────────────

    public function issuePasswordToken(string $username, string $password, string $clientId, string $clientSecret, array $scopes = []): array
    {
        $client = $this->validateClient($clientId, $clientSecret);
        if ($client === null) {
            throw new \InvalidArgumentException('Invalid client credentials');
        }

        // Look up user
        $user = null;
        foreach (self::$users as $u) {
            if ($u['username'] === $username && password_verify($password, $u['password'])) {
                $user = $u;
                break;
            }
        }

        if ($user === null) {
            throw new \InvalidArgumentException('Invalid username or password');
        }

        $allowedScopes = array_intersect($scopes, $client['scopes']);

        return $this->issueAccessTokenForUser($user['id'], $clientId, $allowedScopes);
    }

    /**
     * Register a user for password grant testing.
     */
    public function registerUser(string $username, string $password, string $email = ''): void
    {
        self::$users[] = [
            'id'       => bin2hex(random_bytes(8)),
            'username' => $username,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'email'    => $email,
        ];
    }

    // ── Token Management ─────────────────────────────────────────────────

    protected function issueAccessTokenForUser(?string $userId, string $clientId, array $scopes): array
    {
        $accessToken = bin2hex(random_bytes(40));
        $refreshToken = bin2hex(random_bytes(40));
        $now = time();

        self::$accessTokens[$accessToken] = [
            'client_id'    => $clientId,
            'user_id'      => $userId,
            'scopes'       => $scopes,
            'expires_at'   => $now + $this->accessTokenTtl,
            'created_at'   => $now,
        ];

        self::$refreshTokens[$refreshToken] = [
            'access_token' => $accessToken,
            'expires_at'   => $now + $this->refreshTokenTtl,
            'created_at'   => $now,
        ];

        return [
            'access_token'  => $accessToken,
            'token_type'    => 'Bearer',
            'expires_in'    => $this->accessTokenTtl,
            'refresh_token' => $refreshToken,
            'scope'         => implode(' ', $scopes),
        ];
    }

    public function refreshAccessToken(string $refreshToken): array
    {
        $tokenData = self::$refreshTokens[$refreshToken] ?? null;
        if ($tokenData === null) {
            throw new \InvalidArgumentException('Invalid refresh token');
        }
        if ($tokenData['expires_at'] < time()) {
            throw new \InvalidArgumentException('Refresh token expired');
        }

        $accessTokenData = self::$accessTokens[$tokenData['access_token']] ?? null;
        if ($accessTokenData === null) {
            throw new \InvalidArgumentException('Access token not found');
        }

        // Issue new token pair
        $newAt = bin2hex(random_bytes(40));
        $newRt = bin2hex(random_bytes(40));
        $now = time();

        self::$accessTokens[$newAt] = $accessTokenData;
        self::$accessTokens[$newAt]['expires_at'] = $now + $this->accessTokenTtl;
        self::$accessTokens[$newAt]['created_at'] = $now;

        self::$refreshTokens[$newRt] = [
            'access_token' => $newAt,
            'expires_at'   => $now + $this->refreshTokenTtl,
            'created_at'   => $now,
        ];

        // Revoke old refresh token
        unset(self::$refreshTokens[$refreshToken]);

        return [
            'access_token'  => $newAt,
            'token_type'    => 'Bearer',
            'expires_in'    => $this->accessTokenTtl,
            'refresh_token' => $newRt,
            'scope'         => implode(' ', $accessTokenData['scopes']),
        ];
    }

    public function revokeToken(string $token): void
    {
        unset(self::$accessTokens[$token]);
        foreach (self::$refreshTokens as $rt => $data) {
            if ($data['access_token'] === $token) {
                unset(self::$refreshTokens[$rt]);
            }
        }
    }

    public function verifyAccessToken(string $token): ?array
    {
        $data = self::$accessTokens[$token] ?? null;
        if ($data === null || $data['expires_at'] < time()) {
            return null;
        }
        return $data;
    }

    public function getIntrospection(string $token): array
    {
        $data = $this->verifyAccessToken($token);
        if ($data === null) {
            return ['active' => false];
        }
        return [
            'active'    => true,
            'client_id' => $data['client_id'],
            'user_id'   => $data['user_id'] ?? null,
            'scopes'    => $data['scopes'],
            'exp'       => $data['expires_at'],
        ];
    }

    public function introspect(string $token): array
    {
        return $this->getIntrospection($token);
    }

    // ── User Registration (for testing) ──────────────────────────────────

    public function addUser(string $id, string $username, string $password, string $email = ''): void
    {
        self::$users[] = [
            'id'       => $id,
            'username' => $username,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'email'    => $email,
        ];
    }

    // ── Cleanup / Test Helpers ───────────────────────────────────────────

    public function flush(): void
    {
        self::$clients    = [];
        self::$authCodes  = [];
        self::$accessTokens = [];
        self::$refreshTokens = [];
        self::$users      = [];
    }

    public function getUserById(string $userId): ?array
    {
        foreach (self::$users as $user) {
            if ($user['id'] === $userId) {
                return $user;
            }
        }
        return null;
    }
}
