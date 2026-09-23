<?php
declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Core\Cache;

class CacheTest extends TestCase
{
    private Cache $cache;

    protected function setUp(): void
    {
        $this->cache = new Cache();
    }

    public function testSetAndGet(): void
    {
        $key = 'test_key_' . uniqid();
        $value = 'test_value';

        $this->cache->set($key, $value, 60);
        $this->assertEquals($value, $this->cache->get($key));
    }

    public function testForget(): void
    {
        $key = 'test_key_forget_' . uniqid();
        $value = 'test_value';

        $this->cache->set($key, $value, 60);
        $this->cache->forget($key);

        $this->assertNull($this->cache->get($key));
    }

    public function testExpiration(): void
    {
        $key = 'test_key_expire_' . uniqid();
        $value = 'test_value';

        // Set with 1 second expiration
        $this->cache->set($key, $value, 1);

        // Wait for expiration
        sleep(2);

        $this->assertNull($this->cache->get($key));
    }
}
