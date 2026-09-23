<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\Module;
use App\Core\ModuleRegistry;
use App\Core\Router;
use App\Core\Container;

class ModuleRegistryTest extends TestCase
{
    public function test_register_and_retrieve(): void
    {
        $module = new TestModule();
        $registry = new ModuleRegistry();
        $registry->register($module);

        $this->assertSame($module, $registry->get(TestModule::class));
        $this->assertCount(1, $registry->all());
    }

    public function test_boot_all_calls_boot_on_each_module(): void
    {
        $state = new class {
            /** @var string[] */
            public array $booted = [];
        };

        $module1 = new BootModule($state);
        $module2 = new BootModuleB($state);

        $registry = new ModuleRegistry();
        $registry->register($module1);
        $registry->register($module2);
        $registry->bootAll();

        $this->assertCount(2, $state->booted);
    }

    public function test_register_routes_calls_routes_on_each_module(): void
    {
        $state = new class {
            /** @var string[] */
            public array $routeCalls = [];
        };

        $module = new RouteModule($state);
        $registry = new ModuleRegistry();
        $registry->register($module);

        $container = new Container();
        $router = new Router($container);
        $registry->registerRoutes($router);

        $this->assertCount(1, $state->routeCalls);
    }

    public function test_discover_finds_modules_in_directory(): void
    {
        $tempDir = sys_get_temp_dir() . '/framework_modules_test_' . uniqid();
        mkdir($tempDir, 0755, true);

        $registry = new ModuleRegistry();
        $registry->discover($tempDir, 'NonExistentNamespace');
        $this->assertCount(0, $registry->all());

        rmdir($tempDir);
    }

    public function test_migrations_returns_empty_for_module_without_migrations_dir(): void
    {
        $module = new TestModule();
        $registry = new ModuleRegistry();
        $registry->register($module);
        $this->assertSame([], $registry->allMigrations());
    }

    public function test_seeds_returns_empty_for_module_without_seeds_dir(): void
    {
        $module = new TestModule();
        $registry = new ModuleRegistry();
        $registry->register($module);
        $this->assertSame([], $registry->allSeeds());
    }

    public function test_register_multiple_at_once(): void
    {
        $m1 = new TestModuleA();
        $m2 = new TestModuleB();
        $registry = new ModuleRegistry();
        $registry->registerMultiple($m1, $m2);

        $this->assertCount(2, $registry->all());
    }
}

class TestModule extends Module {}
class TestModuleA extends Module {}
class TestModuleB extends Module {}

class BootModule extends Module
{
    /** @var object */
    private $state;
    public function __construct($state) { $this->state = $state; }
    public function boot(): void { $this->state->booted[] = BootModule::class; }
}

class BootModuleB extends Module
{
    /** @var object */
    private $state;
    public function __construct($state) { $this->state = $state; }
    public function boot(): void { $this->state->booted[] = BootModuleB::class; }
}

class RouteModule extends Module
{
    /** @var object */
    private $state;
    public function __construct($state) { $this->state = $state; }
    public function routes(Router $router): void { $this->state->routeCalls[] = 'registered'; }
}
