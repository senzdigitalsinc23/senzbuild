<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\Hash;

class HashTest extends TestCase
{
    public function test_bcrypt_hash_is_created(): void
    {
        $hash = Hash::make('password');
        $this->assertNotNull($hash);
        $this->assertStringStartsWith('$2y$', $hash);
    }

    public function test_bcrypt_verify(): void
    {
        $hash = Hash::make('password');
        $this->assertTrue(Hash::verify('password', $hash));
        $this->assertFalse(Hash::verify('wrong', $hash));
    }

    public function test_argon2id_hash_when_supported(): void
    {
        if (!Hash::isArgon2Supported()) {
            $this->markTestSkipped('Argon2id not supported on this PHP build');
        }
        $hash = Hash::make('password', ['driver' => 'argon2id']);
        $this->assertNotNull($hash);
        $this->assertTrue(Hash::verify('password', $hash));
    }

    public function test_detects_bcrypt_driver(): void
    {
        $hash = Hash::make('password');
        $this->assertSame('bcrypt', Hash::detectDriver($hash));
    }

    public function test_detects_argon2id_driver(): void
    {
        if (!Hash::isArgon2Supported()) {
            $this->markTestSkipped('Argon2id not supported');
        }
        $hash = Hash::make('password', ['driver' => 'argon2id']);
        $this->assertSame('argon2id', Hash::detectDriver($hash));
    }

    public function test_needs_rehash_for_low_cost_bcrypt(): void
    {
        $lowCost = password_hash('password', PASSWORD_BCRYPT, ['cost' => 7]);
        $this->assertTrue(Hash::needsRehash($lowCost));
    }

    public function test_no_rehash_for_current_cost(): void
    {
        $hash = Hash::make('password');
        $this->assertFalse(Hash::needsRehash($hash));
    }

    public function test_default_driver_is_bcrypt(): void
    {
        $this->assertSame('bcrypt', Hash::getDefaultDriver());
    }

    public function test_can_set_default_driver(): void
    {
        Hash::setDefaultDriver('argon2id');
        $this->assertSame('argon2id', Hash::getDefaultDriver());
        Hash::setDefaultDriver('bcrypt'); // reset
    }
}
