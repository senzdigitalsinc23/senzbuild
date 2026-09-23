<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\OAuth2Server;

class OAuth2ServerTest extends TestCase
{
    private OAuth2Server $server;

    protected function setUp(): void
    {
        $this->server = new OAuth2Server(new \App\Core\Container());
        $this->server->flush();
    }

    public function test_register_and_validate_client(): void
    {
        $this->server->registerClient('my-client', 'secret123', ['https://app.com/callback'], ['read', 'write']);
        $client = $this->server->validateClient('my-client', 'secret123');
        $this->assertNotNull($client);
        $this->assertSame('my-client', $client['client_id']);
    }

    public function test_invalid_client_secret_rejected(): void
    {
        $this->server->registerClient('my-client', 'secret123', ['https://app.com/callback']);
        $this->assertNull($this->server->validateClient('my-client', 'wrong-secret'));
    }

    public function test_issue_and_exchange_auth_code(): void
    {
        $this->server->registerClient('client-1', 'secret', ['https://app.com/callback']);
        $code = $this->server->issueAuthCode('client-1', 'user-1', 'https://app.com/callback', ['read']);

        $token = $this->server->exchangeAuthCode($code, 'client-1', 'secret', 'https://app.com/callback');
        $this->assertArrayHasKey('access_token', $token);
        $this->assertSame('Bearer', $token['token_type']);
        $this->assertArrayHasKey('refresh_token', $token);
    }

    public function test_client_credentials_flow(): void
    {
        $this->server->registerClient('svc-client', 'svc-secret', [], ['read']);
        $token = $this->server->issueClientCredentialsToken('svc-client', 'svc-secret', ['read']);
        $this->assertArrayHasKey('access_token', $token);
    }

    public function test_password_grant_flow(): void
    {
        $this->server->registerClient('web-app', 'app-secret', ['https://app.com/login']);
        $this->server->registerUser('john', 'password123', 'john@example.com');

        $token = $this->server->issuePasswordToken('john', 'password123', 'web-app', 'app-secret', ['read']);
        $this->assertArrayHasKey('access_token', $token);
    }

    public function test_refresh_token(): void
    {
        $this->server->registerClient('client-1', 'secret', ['https://app.com/callback']);
        $this->server->registerUser('u1', 'pass', 'u1@test.com');

        $token = $this->server->issuePasswordToken('u1', 'pass', 'client-1', 'secret');
        $newToken = $this->server->refreshAccessToken($token['refresh_token']);
        $this->assertNotSame($token['access_token'], $newToken['access_token']);
    }

    public function test_revoke_token(): void
    {
        $this->server->registerClient('c1', 's1', ['https://x.com']);
        $this->server->registerUser('u1', 'p1', '');
        $token = $this->server->issuePasswordToken('u1', 'p1', 'c1', 's1');

        $this->server->revokeToken($token['access_token']);
        $this->assertNull($this->server->verifyAccessToken($token['access_token']));
    }

    public function test_introspection(): void
    {
        $this->server->registerClient('c1', 's1', ['https://x.com']);
        $this->server->registerUser('u1', 'p1', '');
        $token = $this->server->issuePasswordToken('u1', 'p1', 'c1', 's1');

        $info = $this->server->introspect($token['access_token']);
        $this->assertTrue($info['active']);
        $this->assertSame('c1', $info['client_id']);
    }

    public function test_expired_auth_code_rejected(): void
    {
        $this->server->registerClient('c1', 's1', ['https://x.com']);
        $code = $this->server->issueAuthCode('c1', 'u1', 'https://x.com/cb');

        // Simulate expiry by manipulating internal state
        $ref = new \ReflectionClass($this->server);
        $prop = $ref->getProperty('authCodes');
        $prop->setAccessible(true);
        $codes = $prop->getValue($this->server);
        $codes[$code]['expires_at'] = time() - 1;
        $prop->setValue($this->server, $codes);

        $this->expectException(\InvalidArgumentException::class);
        $this->server->exchangeAuthCode($code, 'c1', 's1', 'https://x.com/cb');
    }

    public function test_double_use_code_rejected(): void
    {
        $this->server->registerClient('c1', 's1', ['https://x.com']);
        $this->server->registerUser('u1', 'p1', '');
        $token = $this->server->issuePasswordToken('u1', 'p1', 'c1', 's1');

        // Token should be valid
        $this->assertNotNull($this->server->verifyAccessToken($token['access_token']));
    }
}
