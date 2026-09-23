<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\SodiumCrypto;

class SodiumCryptoTest extends TestCase
{
    public function test_encrypt_decrypt_roundtrip(): void
    {
        if (!SodiumCrypto::isSupported()) {
            $this->markTestSkipped('libsodium not available');
        }

        $key = SodiumCrypto::generateSecretKey();
        $encrypted = SodiumCrypto::encrypt('secret data', $key);
        $decrypted = SodiumCrypto::decrypt($encrypted, $key);

        $this->assertSame('secret data', $decrypted);
    }

    public function test_seal_unseal(): void
    {
        if (!SodiumCrypto::isSupported()) {
            $this->markTestSkipped('libsodium not available');
        }

        $pair = SodiumCrypto::generateKeyPair();
        $sealed = SodiumCrypto::seal('confidential', $pair['public_key']);
        $unsealed = SodiumCrypto::unseal($sealed, $pair['key_pair_hex']);

        $this->assertSame('confidential', $unsealed);
    }

    public function test_generate_key_pair(): void
    {
        if (!SodiumCrypto::isSupported()) {
            $this->markTestSkipped('libsodium not available');
        }

        $pair = SodiumCrypto::generateKeyPair();
        $this->assertSame(64, strlen($pair['key_pair_hex']));
        $this->assertSame(32, strlen(hex2bin($pair['key_pair_hex'])));
    }

    public function test_hash(): void
    {
        if (!SodiumCrypto::isSupported()) {
            $this->markTestSkipped('libsodium not available');
        }

        $hash1 = SodiumCrypto::hash('hello');
        $hash2 = SodiumCrypto::hash('hello');
        $this->assertSame($hash1, $hash2);

        $hash3 = SodiumCrypto::hash('world');
        $this->assertNotSame($hash1, $hash3);
    }

    public function test_compare(): void
    {
        if (!SodiumCrypto::isSupported()) {
            $this->markTestSkipped('libsodium not available');
        }

        $this->assertTrue(SodiumCrypto::compare('abc', 'abc'));
        $this->assertFalse(SodiumCrypto::compare('abc', 'def'));
    }

    public function test_random_token(): void
    {
        if (!SodiumCrypto::isSupported()) {
            $this->markTestSkipped('libsodium not available');
        }

        $token = SodiumCrypto::randomToken(32);
        $this->assertSame(64, strlen($token)); // 32 bytes = 64 hex chars
        $this->assertTrue(ctype_xdigit($token));
    }

    public function test_hex_key_normalize(): void
    {
        if (!SodiumCrypto::isSupported()) {
            $this->markTestSkipped('libsodium not available');
        }

        $rawKey = random_bytes(32);
        $hexKey = bin2hex($rawKey);

        $enc1 = SodiumCrypto::encrypt('data', $rawKey);
        $enc2 = SodiumCrypto::encrypt('data', $hexKey);

        // Both should decrypt successfully
        $this->assertSame('data', SodiumCrypto::decrypt($enc1, $rawKey));
        $this->assertSame('data', SodiumCrypto::decrypt($enc2, $hexKey));
    }

    public function test_invalid_key_throws(): void
    {
        if (!SodiumCrypto::isSupported()) {
            $this->markTestSkipped('libsodium not available');
        }

        $this->expectException(\InvalidArgumentException::class);
        SodiumCrypto::encrypt('data', 'short');
    }
}
