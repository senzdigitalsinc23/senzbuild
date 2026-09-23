<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Module system — isolates services, routes, migrations, and providers per module.
 *
 * Usage:
 *   // 1. Create a module class
 *   class SalesModule extends Module
 *   {
 *       public function register(Container $app): void
 *       {
 *           $app->singleton(SalesService::class, fn($c) => new SalesService());
 *       }
 *
 *       public function boot(): void
 *       {
 *           // Post-registration setup (e.g., register event listeners)
 *       }
 *
 *       public function routes Router $router): void
 *       {
 *           $router->getApi('v1', '/sales', [SalesController::class, 'index']);
 *       }
 *   }
 *
 *   // 2. Register the module
 *   $app->registerModule(new SalesModule());
 *
 * Modules auto-discover from App/Modules/ directory if MODULE_AUTO_DISCOVER=true.
 */
abstract class Module
{
    /**
     * Register services with the container.
     */
    public function register(Container $app): void
    {
        // Override in child class
    }

    /**
     * Boot the module after all services are registered.
     * Called once during application bootstrap.
     */
    public function boot(): void
    {
        // Override in child class
    }

    /**
     * Register routes for this module.
     */
    public function routes(Router $router): void
    {
        // Override in child class
    }

    /**
     * Return migration files for this module.
     * Must return an array of absolute paths to migration PHP files.
     */
    public function migrations(): array
    {
        $moduleDir = dirname((new \ReflectionClass($this))->getFileName());
        $path = $moduleDir . '/migrations';
        if (!is_dir($path)) {
            return [];
        }
        $files = [];
        foreach (glob("{$path}/*.php") as $file) {
            $files[] = $file;
        }
        sort($files);
        return $files;
    }

    /**
     * Return seed files for this module.
     */
    public function seeds(): array
    {
        $moduleDir = dirname((new \ReflectionClass($this))->getFileName());
        $path = $moduleDir . '/seeds';
        if (!is_dir($path)) {
            return [];
        }
        $files = [];
        foreach (glob("{$path}/*.php") as $file) {
            $files[] = $file;
        }
        sort($files);
        return $files;
    }
}

/**
 * Module registry — manages discovery, registration, and lifecycle of modules.
 */
class ModuleRegistry
{
    /** @var Module[] */
    protected array $modules = [];

    /**
     * Register a module instance.
     */
    public function register(Module $module): void
    {
        $name = $module::class;
        $this->modules[$name] = $module;
    }

    /**
     * Register multiple modules at once.
     *
     * @param Module ...$modules
     */
    public function registerMultiple(Module ...$modules): void
    {
        foreach ($modules as $module) {
            $this->register($module);
        }
    }

    /**
     * Auto-discover modules from a directory.
     * Scans for classes that extend Module and are not abstract.
     *
     * @param string $directory Directory to scan
     * @param string $namespace Base namespace
     */
    public function discover(string $directory, string $namespace = 'App\\Modules'): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (glob("{$directory}/*.php") as $file) {
            $className = $namespace . '\\' . basename($file, '.php');
            if (class_exists($className) && is_subclass_of($className, Module::class)) {
                $ref = new \ReflectionClass($className);
                if (!$ref->isAbstract()) {
                    $this->register(new $className());
                }
            }
        }
    }

    /**
     * Get all registered modules.
     *
     * @return Module[]
     */
    public function all(): array
    {
        return $this->modules;
    }

    /**
     * Get a specific module by class name.
     */
    public function get(string $className): ?Module
    {
        return $this->modules[$className] ?? null;
    }

    /**
     * Boot all registered modules.
     */
    public function bootAll(): void
    {
        foreach ($this->modules as $module) {
            $module->boot();
        }
    }

    /**
     * Register routes for all modules.
     */
    public function registerRoutes(Router $router): void
    {
        foreach ($this->modules as $module) {
            $module->routes($router);
        }
    }

    /**
     * Collect migration files from all modules.
     *
     * @return string[]
     */
    public function allMigrations(): array
    {
        $migrations = [];
        foreach ($this->modules as $module) {
            $migrations = array_merge($migrations, $module->migrations());
        }
        sort($migrations);
        return $migrations;
    }

    /**
     * Collect seed files from all modules.
     *
     * @return string[]
     */
    public function allSeeds(): array
    {
        $seeds = [];
        foreach ($this->modules as $module) {
            $seeds = array_merge($seeds, $module->seeds());
        }
        sort($seeds);
        return $seeds;
    }
}
