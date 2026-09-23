<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\MemcachedCache;

class MemcachedCacheTest extends TestCase
{
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(MemcachedCache::class));
    }

    public function test_implements_psr16(): void
    {
        $ref = new \ReflectionClass(MemcachedCache::class);
        $interfaces = $ref->getInterfaceNames();
        $this->assertContains(\Psr\Cache\CacheItemPoolInterface::class, $interfaces);
    }

    public function test_default_construction(): void
    {
        // Should not throw even without memcached extension
        $cache = new MemcachedCache();
        $this->assertTrue(method_exists($cache, 'set'));
        $this->assertTrue(method_exists($cache, 'get'));
    }
}
