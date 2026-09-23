<?php

namespace Tests\Unit\Services;

use App\DTOs\LoginRequestDTO;
use App\DTOs\RegisterRequestDTO;
use App\DTOs\UserDTO;
use App\Exceptions\AuthException;
use App\Models\RefreshToken;
use App\Repositories\AuthUserRepository;
use App\Repositories\RefreshTokenRepository;
use App\Services\AuthService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(AuthService::class)]
class AuthServiceTest extends TestCase
{
    private AuthUserRepository|\PHPUnit\Framework\MockObject\MockObject $repo;
    private RefreshTokenRepository|\PHPUnit\Framework\MockObject\MockObject $refreshRepo;
    private AuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['JWT_SECRET']       = 'test_secret_key_for_jwt_signing_1234567890';
        $_ENV['JWT_TTL']          = '86400';
        $_ENV['JWT_REFRESH_TTL']  = '2592000';
        $this->repo        = $this->createMock(AuthUserRepository::class);
        $this->refreshRepo = $this->createMock(RefreshTokenRepository::class);
        $this->service     = new AuthService($this->repo, $this->refreshRepo);
    }

    private function makeUser(array $overrides = []): UserDTO
    {
        return UserDTO::fromArray(array_merge([
            'id'            => 'u-' . bin2hex(random_bytes(8)),
            'name'          => 'Test User',
            'email'         => 'test@example.com',
            'password'      => password_hash('correct_password', PASSWORD_BCRYPT),
            'role_id'       => 'cashier',
            'store_scope'   => 'all',
            'is_active'     => true,
        ], $overrides));
    }

    /**
     * Helper: set up refreshRepo to return a token when generateRefreshToken is called internally.
     */
    private function mockRefreshTokenCreation(UserDTO $user): RefreshToken
    {
        $rt = new RefreshToken([
            'user_id'    => $user->id,
            'revoked_at' => null,
            'expires_at' => time() + 2592000,
        ]);

        $this->refreshRepo->method('create')
            ->with($user->id, $this->isType('string'))
            ->willReturn($rt);
        return $rt;
    }

    #[Test]
    public function login_returns_auth_response_for_valid_credentials(): void
    {
        $user = $this->makeUser();
        $this->repo->method('findByEmail')->with('test@example.com')->willReturn($user);
        $this->repo->method('updateLastLogin')->with($user->id);
        $this->mockRefreshTokenCreation($user);

        $dto = LoginRequestDTO::fromArray([
            'email'    => 'test@example.com',
            'password' => 'correct_password',
        ]);

        $result = $this->service->login($dto);

        $this->assertSame($user->id, $result->user->id);
        $this->assertNotEmpty($result->token);
        $this->assertNotEmpty($result->refreshToken);
        $this->assertSame(86400, $result->expiresIn);
    }

    #[Test]
    public function login_throws_for_wrong_password(): void
    {
        $user = $this->makeUser();
        $this->repo->method('findByEmail')->willReturn($user);

        $dto = LoginRequestDTO::fromArray([
            'email'    => 'test@example.com',
            'password' => 'wrong_password',
        ]);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Invalid email or password');
        $this->service->login($dto);
    }

    #[Test]
    public function login_throws_for_nonexistent_user(): void
    {
        $this->repo->method('findByEmail')->with('unknown@example.com')->willReturn(null);

        $dto = LoginRequestDTO::fromArray([
            'email'    => 'unknown@example.com',
            'password' => 'any_password',
        ]);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Invalid email or password');
        $this->service->login($dto);
    }

    #[Test]
    public function login_throws_for_inactive_account(): void
    {
        $user = $this->makeUser(['is_active' => false]);
        $this->repo->method('findByEmail')->willReturn($user);

        $dto = LoginRequestDTO::fromArray([
            'email'    => 'test@example.com',
            'password' => 'correct_password',
        ]);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Account is inactive');
        $this->service->login($dto);
    }

    #[Test]
    public function login_updates_last_login_on_success(): void
    {
        $user = $this->makeUser();
        $this->repo->method('findByEmail')->with('test@example.com')->willReturn($user);
        $this->repo->expects($this->once())->method('updateLastLogin')->with($user->id);
        $this->mockRefreshTokenCreation($user);

        $dto = LoginRequestDTO::fromArray([
            'email'    => 'test@example.com',
            'password' => 'correct_password',
        ]);

        $this->service->login($dto);
    }

    #[Test]
    public function register_creates_user_and_returns_auth_response(): void
    {
        $user = $this->makeUser();
        $this->repo->method('emailExists')->with('new@example.com')->willReturn(false);
        $this->repo->method('create')
            ->with($this->isInstanceOf(\App\DTOs\UserCreateDTO::class))
            ->willReturn($user);
        $this->mockRefreshTokenCreation($user);

        $dto = RegisterRequestDTO::fromArray([
            'name'     => 'New User',
            'email'    => 'new@example.com',
            'password' => 'password123',
            'role_id'  => 'cashier',
        ]);

        $result = $this->service->register($dto);

        $this->assertSame($user->id, $result->user->id);
        $this->assertNotEmpty($result->token);
        $this->assertNotEmpty($result->refreshToken);
    }

    #[Test]
    public function register_throws_for_duplicate_email(): void
    {
        $this->repo->method('emailExists')->with('existing@example.com')->willReturn(true);

        $dto = RegisterRequestDTO::fromArray([
            'name'     => 'Existing',
            'email'    => 'existing@example.com',
            'password' => 'password123',
            'role_id'  => 'cashier',
        ]);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('already registered');
        $this->service->register($dto);
    }

    #[Test]
    public function validate_token_returns_user_for_valid_token(): void
    {
        $user = $this->makeUser();
        $this->repo->method('findByEmail')->with('test@example.com')->willReturn($user);
        $this->repo->method('updateLastLogin')->with($user->id);
        $this->mockRefreshTokenCreation($user);
        $this->repo->method('findById')->with($user->id)->willReturn($user);

        $dto = LoginRequestDTO::fromArray([
            'email'    => 'test@example.com',
            'password' => 'correct_password',
        ]);
        $authResponse = $this->service->login($dto);

        $result = $this->service->validateToken($authResponse->token);

        $this->assertNotNull($result);
        $this->assertSame($user->id, $result->id);
    }

    #[Test]
    public function validate_token_returns_null_for_garbage_token(): void
    {
        $result = $this->service->validateToken('this.is.not.a.valid.jwt');
        $this->assertNull($result);
    }

    #[Test]
    public function validate_token_returns_null_for_empty_string(): void
    {
        $result = $this->service->validateToken('');
        $this->assertNull($result);
    }

    #[Test]
    public function validate_token_returns_null_for_expired_token(): void
    {
        $user = $this->makeUser();
        $this->repo->method('findById')->with($user->id)->willReturn($user);

        // Manually create an already-expired JWT
        $expiredToken = \Firebase\JWT\JWT::encode([
            'iss'  => 'test',
            'sub'  => $user->id,
            'iat'  => time() - 3600,
            'exp'  => time() - 1,
            'role' => $user->roleId,
        ], $_ENV['JWT_SECRET'], 'HS256');

        $result = $this->service->validateToken($expiredToken);
        $this->assertNull($result);
    }

    #[Test]
    public function currentUser_wraps_validateToken(): void
    {
        $user = $this->makeUser();
        $this->repo->method('findByEmail')->with('test@example.com')->willReturn($user);
        $this->repo->method('updateLastLogin')->with($user->id);
        $this->mockRefreshTokenCreation($user);
        $this->repo->method('findById')->with($user->id)->willReturn($user);

        $dto = LoginRequestDTO::fromArray([
            'email'    => 'test@example.com',
            'password' => 'correct_password',
        ]);
        $authResponse = $this->service->login($dto);

        $result = $this->service->currentUser($authResponse->token);
        $this->assertNotNull($result);
        $this->assertSame($user->id, $result->id);
    }

    // ── Refresh token tests ──────────────────────────────────────────────────

    #[Test]
    public function refresh_returns_new_token_pair_for_valid_refresh_token(): void
    {
        $user = $this->makeUser();
        $this->repo->method('findById')->with($user->id)->willReturn($user);

        $oldTokenModel = new RefreshToken([
            'user_id'    => $user->id,
            'revoked_at' => null,
            'expires_at' => time() + 2592000,
        ]);

        $this->refreshRepo->method('findByToken')
            ->with('old_refresh_token')
            ->willReturn($oldTokenModel);

        $this->mockRefreshTokenCreation($user);

        $result = $this->service->refresh('old_refresh_token');

        $this->assertNotEmpty($result->token);
        $this->assertNotEmpty($result->refreshToken);
        $this->assertNotSame('old_refresh_token', $result->refreshToken);
        $this->assertSame($user->id, $result->user->id);
    }

    #[Test]
    public function refresh_throws_for_invalid_refresh_token(): void
    {
        $this->refreshRepo->method('findByToken')->with('bad_token')->willReturn(null);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Invalid or expired refresh token');
        $this->service->refresh('bad_token');
    }

    #[Test]
    public function refresh_revokes_old_token_and_returns_new_pair(): void
    {
        $user = $this->makeUser();
        $this->repo->method('findById')->with($user->id)->willReturn($user);

        $oldTokenModel = new RefreshToken([
            'user_id'    => $user->id,
            'revoked_at' => null,
            'expires_at' => time() + 2592000,
        ]);

        $this->refreshRepo->method('findByToken')->with('rotate_me')->willReturn($oldTokenModel);
        $this->refreshRepo->expects($this->once())->method('create')
            ->with($user->id, $this->isType('string'));
        $this->refreshRepo->expects($this->once())->method('revokeByToken')
            ->with('rotate_me');

        $this->service->refresh('rotate_me');
    }

    #[Test]
    public function refresh_throws_when_user_is_inactive(): void
    {
        $inactiveUser = $this->makeUser(['is_active' => false]);
        $this->repo->method('findById')->with('some-user-id')->willReturn($inactiveUser);

        $oldTokenModel = new RefreshToken([
            'user_id'    => 'some-user-id',
            'revoked_at' => null,
            'expires_at' => time() + 2592000,
        ]);

        $this->refreshRepo->method('findByToken')->with('active_token')->willReturn($oldTokenModel);
        $this->refreshRepo->method('revokeByToken')->with('active_token')->willReturn(true);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Account is inactive');
        $this->service->refresh('active_token');
    }

    #[Test]
    public function logout_revokes_the_given_refresh_token(): void
    {
        $this->refreshRepo->expects($this->once())
            ->method('revokeByToken')
            ->with('my_refresh_token')
            ->willReturn(true);

        $this->service->logout('my_refresh_token');
    }

    #[Test]
    public function logout_all_revokes_all_tokens_for_user(): void
    {
        $this->refreshRepo->expects($this->once())
            ->method('revokeAllForUser')
            ->with('user-123')
            ->willReturn(5);

        $result = $this->service->logoutAll('user-123');
        $this->assertSame(5, $result);
    }

    #[Test]
    public function auth_response_to_array_includes_refresh_token(): void
    {
        $user = $this->makeUser();
        $this->repo->method('findByEmail')->with('test@example.com')->willReturn($user);
        $this->repo->method('updateLastLogin')->with($user->id);
        $this->mockRefreshTokenCreation($user);

        $dto = LoginRequestDTO::fromArray([
            'email'    => 'test@example.com',
            'password' => 'correct_password',
        ]);
        $result = $this->service->login($dto);
        $arr    = $result->toArray();

        $this->assertArrayHasKey('refresh_token', $arr);
        $this->assertNotEmpty($arr['refresh_token']);
        $this->assertArrayNotHasKey('password', $arr['user']);
    }
}
