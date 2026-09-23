<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\RouteCache;
use App\Core\Router;
use App\Core\Container;

class RouteCacheTest extends TestCase
{
    private string $cachePath;

    protected function setUp(): void
    {
        $this->cachePath = sys_get_temp_dir() . '/route_cache_test_' . uniqid() . '.php';
        RouteCache::forceCachePath($this->cachePath);
    }

    protected function tearDown(): void
    {
        @unlink($this->cachePath);
    }

    public function test_compile_and_load(): void
    {
        $container = new Container();
        $router = new Router($container);

        // Add a route
        $router->get('/test', [\stdClass::class, 'method']);

        // Compile cache
        RouteCache::compile($router);
        $this->assertTrue(RouteCache::hasCache());

        // Create new router and load cache
        $router2 = new Router($container);
        RouteCache::load($router2);

        $ref = new \ReflectionClass($router2);
        $prop = $ref->getProperty('routes');
        $prop->setAccessible(true);
        $routes = $prop->getValue($router2);

        $this->assertCount(1, $routes);
        $this->assertSame('/test', $routes[0]['uri']);
    }

    public function test_clear(): void
    {
        $container = new Container();
        $router = new Router($container);
        $router->get('/test', [\stdClass::class, 'method']);
        RouteCache::compile($router);
        $this->assertTrue(RouteCache::hasCache());

        RouteCache::clear();
        $this->assertFalse(RouteCache::hasCache());
    }

    public function test_path(): void
    {
        $this->assertSame($this->cachePath, RouteCache::getPath());
    }

    public function test_compiled_at(): void
    {
        $container = new Container();
        $router = new Router($container);
        RouteCache::compile($router);

        $at = RouteCache::getCompiledAt();
        $this->assertNotNull($at);
        $this->assertIsInt($at);
    }
}
