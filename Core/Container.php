<?php
declare(strict_types=1);
namespace App\Core;

use App\Core\Interfaces\ContainerInterface;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use ReflectionClass;
use Exception;

class Container implements ContainerInterface
{
    protected array $bindings = [];
    protected array $instances = [];
    /** @var array<string, bool> Tracks which entries are registered as lazy */
    protected array $lazyBindings = [];

    public function bind(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = $factory;
        unset($this->lazyBindings[$abstract]);
    }

    /**
     * Bind a factory that will only resolve when the service is first requested.
     * Unlike singleton(), lazy bindings are NOT cached — each get() calls the factory.
     */
    public function bindLazy(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = $factory;
        $this->lazyBindings[$abstract] = true;
    }

    public function singleton(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = $factory;
        $this->instances[$abstract] = null;
        unset($this->lazyBindings[$abstract]);
    }

    /**
     * Register a factory that returns a proxy object — the real service is resolved on first method call.
     */
    public function singletonProxy(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = $factory;
        $this->instances[$abstract] = null;

        // Override get() for this abstract to return a proxy
        $proxy = new class($factory, $this) {
            private $factory;
            private $container;
            private $instance = null;

            public function __construct(callable $factory, Container $container)
            {
                $this->factory = $factory;
                $this->container = $container;
            }

            public function __call(string $name, array $args): mixed
            {
                if ($this->instance === null) {
                    $this->instance = ($this->factory)($this->container);
                }
                return $this->instance->$name(...$args);
            }

            public function __get(string $name): mixed
            {
                if ($this->instance === null) {
                    $this->instance = ($this->factory)($this->container);
                }
                return $this->instance->$name;
            }

            public function __isset(string $name): bool
            {
                if ($this->instance === null) {
                    $this->instance = ($this->factory)($this->container);
                }
                return isset($this->instance->$name);
            }
        };

        // Store the proxy factory so resolve() can return it
        $this->bindings[$abstract] = function (Container $c) use ($proxy) {
            return $proxy;
        };
    }

    public function get(string $id): mixed
    {
        return $this->resolve($id);
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || class_exists($id);
    }

    public function resolve(string $abstract): mixed
    {
        // Check singleton cache first
        if (array_key_exists($abstract, $this->instances) && $this->instances[$abstract] !== null) {
            return $this->instances[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            $isLazy = isset($this->lazyBindings[$abstract]);

            $object = $this->bindings[$abstract]($this);

            // Only cache non-lazy singletons
            if (!$isLazy && array_key_exists($abstract, $this->instances)) {
                $this->instances[$abstract] = $object;
            }
            return $object;
        }

        if (!class_exists($abstract)) {
            throw new Exception("Class {$abstract} is not instantiable");
        }

        $reflector = new ReflectionClass($abstract);
        if (!$reflector->isInstantiable()) {
            throw new Exception("Class {$abstract} is not instantiable");
        }

        $constructor = $reflector->getConstructor();
        if (!$constructor) {
            return new $abstract;
        }

        $dependencies = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();
            if ($type && !$type->isBuiltin()) {
                $typeName = $type->getName();
                // Force-autoload the type so interface_exists / class_exists works
                class_exists($typeName, true);
                interface_exists($typeName, true);
                // If the parameter type-hints an interface this class implements, inject self
                if (interface_exists($typeName, true)) {
                    $ref = new ReflectionClass($abstract);
                    if ($ref->implementsInterface($typeName) || $this instanceof \Psr\Container\ContainerInterface) {
                        $dependencies[] = $this;
                        continue;
                    }
                    // Other interfaces: skip with default or null
                    $dependencies[] = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;
                    continue;
                }
                // Lazy-resolve dependencies only if they're registered as lazy
                if (isset($this->lazyBindings[$typeName])) {
                    $dependencies[] = $this->resolve($typeName);
                } else {
                    $dependencies[] = $this->resolve($typeName);
                }
            } elseif ($param->isDefaultValueAvailable()) {
                $dependencies[] = $param->getDefaultValue();
            } else {
                throw new Exception("Unresolvable dependency: {$param->getName()}");
            }
        }

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Clear a singleton instance so it will be re-resolved on next get().
     */
    public function forget(string $abstract): void
    {
        unset($this->instances[$abstract]);
    }

    /**
     * Clear all cached singleton instances.
     */
    public function flush(): void
    {
        $this->instances = [];
    }

    public function register(string $providerClass): void
    {
        if (!is_subclass_of($providerClass, \App\Core\Interfaces\ServiceProviderInterface::class)) {
            throw new Exception("Provider {$providerClass} must implement ServiceProviderInterface");
        }

        $provider = $this->resolve($providerClass);
        $provider->register($this);
    }
}
