<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\OAuth2Server;
use App\Core\SodiumCrypto;
use App\Core\ApiKeyRotation;

class SecurityFeaturesTest extends TestCase
{
    protected function tearDown(): void
    {
        OAuth2Server::class; // no cleanup needed
    }

    public function test_oauth2_auth_code_flow(): void
    {
        $server = new OAuth2Server(new \App\Core\Container());
        $server->registerClient('client-1', 'secret', ['https://app.com/callback']);
        $server->registerUser('user-1', 'password', 'user@test.com');

        $code = $server->issueAuthCode('client-1', 'user-1', 'https://app.com/callback', ['read']);
        $this->assertNotEmpty($code);

        $token = $server->exchangeAuthCode($code, 'client-1', 'secret', 'https://app.com/callback');
        $this->assertArrayHasKey('access_token', $token);
        $this->assertSame('Bearer', $token['token_type']);
    }

    public function test_oauth2_password_grant(): void
    {
        $server = new OAuth2Server(new \App\Core\Container());
        $server->registerClient('web-app', 'secret', []);
        $server->registerUser('admin', 'admin123', 'admin@test.com');

        $token = $server->issuePasswordToken('admin', 'admin123', 'web-app', 'secret');
        $this->assertArrayHasKey('access_token', $token);
    }

    public function test_oauth2_refresh_token(): void
    {
        $server = new OAuth2Server(new \App\Core\Container());
        $server->registerClient('c1', 's1', ['https://x.com']);
        $server->registerUser('u1', 'p1', '');
        $token = $server->issuePasswordToken('u1', 'p1', 'c1', 's1');

        $newToken = $server->refreshAccessToken($token['refresh_token']);
        $this->assertNotSame($token['access_token'], $newToken['access_token']);
    }

    public function test_sodium_encrypt_decrypt(): void
    {
        if (!SodiumCrypto::isSupported()) {
            $this->markTestSkipped('libsodium not available');
        }

        $key = SodiumCrypto::generateSecretKey();
        $encrypted = SodiumCrypto::encrypt('secret', $key);
        $decrypted = SodiumCrypto::decrypt($encrypted, $key);
        $this->assertSame('secret', $decrypted);
    }

    public function test_api_key_rotation_generate(): void
    {
        $rotator = new ApiKeyRotation();
        $key = $rotator->generate('test-key', ['read', 'write']);
        $this->assertStringStartsWith('sk_', $key['key']);
        $this->assertSame('test-key', $key['name']);
    }

    public function test_api_key_rotation_verify(): void
    {
        $rotator = new ApiKeyRotation();
        $record = $rotator->generate('verify-key', ['read']);
        $verified = $rotator->verify($record['key']);
        $this->assertNotNull($verified);
        $this->assertSame('verify-key', $verified['name']);
    }
}
