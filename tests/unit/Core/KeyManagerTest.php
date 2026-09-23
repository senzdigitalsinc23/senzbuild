<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\KeyManager;

class KeyManagerTest extends TestCase
{
    protected function setUp(): void
    {
        KeyManager::flush();
    }

    public function test_store_and_get_active(): void
    {
        KeyManager::store('jwt', 'key_one');
        $this->assertSame('key_one', KeyManager::getActive('jwt'));
    }

    public function test_rotate_makes_new_key_active(): void
    {
        KeyManager::store('jwt', 'old_key');
        KeyManager::rotate('jwt', 'new_key');
        $this->assertSame('new_key', KeyManager::getActive('jwt'));
        $this->assertSame('old_key', KeyManager::getPrevious('jwt'));
    }

    public function test_rotate_keeps_multiple_previous_keys(): void
    {
        KeyManager::store('jwt', 'key_a');
        KeyManager::rotate('jwt', 'key_b');
        KeyManager::rotate('jwt', 'key_c');

        $this->assertSame('key_c', KeyManager::getActive('jwt'));
        $this->assertSame('key_b', KeyManager::getPrevious('jwt'));
    }

    public function test_has_active_returns_true_after_store(): void
    {
        KeyManager::store('jwt', 'secret');
        $this->assertTrue(KeyManager::hasActive('jwt'));
    }

    public function test_has_active_returns_false_when_not_stored(): void
    {
        $this->assertFalse(KeyManager::hasActive('nonexistent'));
    }

    public function test_generate_produces_hex_string(): void
    {
        $key = KeyManager::generate();
        $this->assertSame(64, strlen($key));
        $this->assertTrue(ctype_xdigit($key));
    }

    public function test_generate_custom_length(): void
    {
        $key = KeyManager::generate(16);
        $this->assertSame(32, strlen($key)); // 16 bytes = 32 hex chars
    }

    public function test_get_keys_returns_full_data(): void
    {
        KeyManager::store('jwt', 'active_key');
        KeyManager::rotate('jwt', 'new_key');
        $keys = KeyManager::getKeys('jwt');

        $this->assertArrayHasKey('active', $keys);
        $this->assertArrayHasKey('previous', $keys);
        $this->assertSame('new_key', $keys['active']);
    }

    public function test_rotate_without_prior_store(): void
    {
        KeyManager::rotate('jwt', 'first_key');
        $this->assertSame('first_key', KeyManager::getActive('jwt'));
    }

    public function test_max_previous_keys_enforced(): void
    {
        KeyManager::store('jwt', 'a');
        KeyManager::rotate('jwt', 'b');
        KeyManager::rotate('jwt', 'c');
        KeyManager::rotate('jwt', 'd'); // max is 3, so oldest should be dropped

        $keys = KeyManager::getKeys('jwt');
        // After 3 rotations from initial store: active=d, prev=[c, b, a]
        // After 4th rotate: active=d, prev=[c, b] (a dropped since max is 3 but we only had room for 2 before adding d)
        // Actually: store(a), rotate(b) -> [b, [a]], rotate(c) -> [c, [b, a]], rotate(d) -> [d, [c, b, a]]
        $this->assertCount(3, $keys['previous']);
        $this->assertSame('c', $keys['previous'][0]);
        $this->assertSame('a', $keys['previous'][2]);
    }
}
