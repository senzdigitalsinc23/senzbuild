<?php
declare(strict_types=1);

namespace App\Controllers\Api\v1;

use App\Core\Request;
use App\Core\Response;
use App\Core\Traits\JsonResponseTrait;
use App\DTOs\LoginRequestDTO;
use App\DTOs\RegisterRequestDTO;
use App\Exceptions\AuthException;
use App\Services\AuthService;

/**
 * API v1 Authentication Controller.
 *
 * Endpoints:
 *   POST /api/v1/auth/register
 *   POST /api/v1/auth/login
 *   POST /api/v1/auth/refresh
 *   POST /api/v1/auth/logout
 *   POST /api/v1/auth/logout-all
 *   GET  /api/v1/auth/me
 */
class AuthController
{
    use JsonResponseTrait;

    private AuthService $authService;

    public function __construct(?AuthService $authService = null)
    {
        $this->authService = $authService ?? new AuthService();
    }

    // ── POST /api/v1/auth/register ──────────────────────────────────────────

    /**
     * Register a new user and return access + refresh tokens.
     */
    public function register(Request $request, Response $response): Response
    {
        try {
            $dto = RegisterRequestDTO::fromArray($request->getBodyParams());
            $result = $this->authService->register($dto);
            return $this->json($response, 201, [
                'success' => true,
                'data'    => $result->toArray(),
                'message' => 'Registration successful',
            ]);
        } catch (AuthException $e) {
            return $this->json($response, $e->getStatusCode(), [
                'success' => false,
                'error'   => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            return $this->json($response, 500, [
                'success' => false,
                'error'   => 'REGISTRATION_ERROR',
                'message' => 'Registration failed',
            ]);
        }
    }

    // ── POST /api/v1/auth/login ─────────────────────────────────────────────

    /**
     * Login and return access + refresh tokens.
     */
    public function login(Request $request, Response $response): Response
    {
        try {
            $dto = LoginRequestDTO::fromArray($request->getBodyParams());
            $result = $this->authService->login($dto);
            return $this->json($response, 200, [
                'success' => true,
                'data'    => $result->toArray(),
                'message' => 'Login successful',
            ]);
        } catch (AuthException $e) {
            return $this->json($response, $e->getStatusCode(), [
                'success' => false,
                'error'   => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            return $this->json($response, 500, [
                'success' => false,
                'error'   => 'LOGIN_ERROR',
                'message' => 'Login failed',
            ]);
        }
    }

    // ── POST /api/v1/auth/refresh ───────────────────────────────────────────

    /**
     * Exchange a refresh token for a new access token + rotated refresh token.
     */
    public function refresh(Request $request, Response $response): Response
    {
        try {
            $body      = $request->getBodyParams();
            $token     = $body['refresh_token'] ?? $body['refreshToken'] ?? '';

            if (empty($token)) {
                return $this->json($response, 400, [
                    'success' => false,
                    'error'   => 'MISSING_REFRESH_TOKEN',
                    'message' => 'refresh_token is required',
                ]);
            }

            $result = $this->authService->refresh($token);
            return $this->json($response, 200, [
                'success' => true,
                'data'    => $result->toArray(),
                'message' => 'Token refreshed successfully',
            ]);
        } catch (AuthException $e) {
            return $this->json($response, $e->getStatusCode(), [
                'success' => false,
                'error'   => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            return $this->json($response, 500, [
                'success' => false,
                'error'   => 'REFRESH_ERROR',
                'message' => 'Token refresh failed',
            ]);
        }
    }

    // ── POST /api/v1/auth/logout ────────────────────────────────────────────

    /**
     * Revoke the current refresh token (logout from this device).
     * Requires a valid Bearer access token.
     */
    public function logout(Request $request, Response $response): Response
    {
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
            return $this->json($response, 401, [
                'success' => false,
                'error'   => 'UNAUTHORIZED',
                'message' => 'Bearer token required',
            ]);
        }

        try {
            $body         = $request->getBodyParams();
            $refreshToken = $body['refresh_token'] ?? $body['refreshToken'] ?? '';

            if (empty($refreshToken)) {
                return $this->json($response, 400, [
                    'success' => false,
                    'error'   => 'MISSING_REFRESH_TOKEN',
                    'message' => 'refresh_token is required',
                ]);
            }

            $this->authService->logout($refreshToken);
            return $this->json($response, 200, [
                'success' => true,
                'message' => 'Logged out successfully',
            ]);
        } catch (\Throwable $e) {
            return $this->json($response, 500, [
                'success' => false,
                'error'   => 'LOGOUT_ERROR',
                'message' => 'Logout failed',
            ]);
        }
    }

    // ── POST /api/v1/auth/logout-all ────────────────────────────────────────

    /**
     * Revoke ALL refresh tokens for the current user (logout from all devices).
     * Requires a valid Bearer access token.
     */
    public function logoutAll(Request $request, Response $response): Response
    {
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
            return $this->json($response, 401, [
                'success' => false,
                'error'   => 'UNAUTHORIZED',
                'message' => 'Bearer token required',
            ]);
        }

        try {
            $user = $this->authService->currentUser($m[1]);
            if (!$user) {
                return $this->json($response, 401, [
                    'success' => false,
                    'error'   => 'INVALID_TOKEN',
                    'message' => 'Invalid or expired token',
                ]);
            }

            $revoked = $this->authService->logoutAll($user->id);
            return $this->json($response, 200, [
                'success'  => true,
                'message'  => "Logged out from {$revoked} device(s)",
                'revoked_count' => $revoked,
            ]);
        } catch (\Throwable $e) {
            return $this->json($response, 500, [
                'success' => false,
                'error'   => 'LOGOUT_ALL_ERROR',
                'message' => 'Logout all failed',
            ]);
        }
    }

    // ── GET /api/v1/auth/me ─────────────────────────────────────────────────

    /**
     * Return the currently authenticated user's profile.
     */
    public function me(Request $request, Response $response): Response
    {
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
            return $this->json($response, 401, [
                'success' => false,
                'error'   => 'UNAUTHORIZED',
                'message' => 'Bearer token required',
            ]);
        }

        $user = $this->authService->currentUser($m[1]);
        if (!$user) {
            return $this->json($response, 401, [
                'success' => false,
                'error'   => 'INVALID_TOKEN',
                'message' => 'Invalid or expired token',
            ]);
        }

        return $this->json($response, 200, [
            'success' => true,
            'data'    => $user->toArrayWithoutPassword(),
        ]);
    }
}
