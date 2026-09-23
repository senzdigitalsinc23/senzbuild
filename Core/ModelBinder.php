<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Model Binding — automatically resolve route parameters to model instances.
 *
 * Usage:
 *   // In routes:
 *   $router->get('/users/{user}', [UserController::class, 'show'], [], User::class);
 *
 *   // Or with explicit binding:
 *   Route::bind('user', function($id) { return User::find($id); });
 *
 *   // In controller:
 *   public function show(Request $request, Response $response, array $params) {
 *       $user = $params['user']; // User instance, not string ID
 *   }
 */
class ModelBinder
{
    protected static array $bindings = [];
    protected static array $wildcards = [];

    /**
     * Register a model binding for a route parameter.
     *
     * @param string $param Parameter name (e.g., 'user')
     * @param string $modelClass Model class to instantiate
     * @param string|null $keyColumn Column to query by (defaults to 'id')
     */
    public static function bind(string $param, string $modelClass, string $keyColumn = 'id'): void
    {
        self::$bindings[$param] = [
            'class'      => $modelClass,
            'key_column' => $keyColumn,
        ];
    }

    /**
     * Register a custom resolver closure for a parameter.
     *
     * @param string $param
     * @param callable $resolver Closure(value, Request) => mixed
     */
    public static function bindResolver(string $param, callable $resolver): void
    {
        self::$bindings[$param] = [
            'resolver' => $resolver,
        ];
    }

    /**
     * Resolve all bound parameters from a route match.
     *
     * @param array $params Raw route parameters from URL
     * @param Request|null $request Optional request for context
     * @return array Resolved parameters
     */
    public static function resolve(array $params, ?Request $request = null): array
    {
        $resolved = [];
        foreach ($params as $key => $value) {
            $binding = self::$bindings[$key] ?? null;
            if ($binding === null) {
                $resolved[$key] = $value;
                continue;
            }

            if (isset($binding['class'])) {
                $modelClass = $binding['class'];
                $keyCol = $binding['key_column'] ?? 'id';
                $instance = $modelClass::where($keyCol, $value);
                $resolved[$key] = $instance;
            } elseif (isset($binding['resolver'])) {
                $resolved[$key] = ($binding['resolver'])($value, $request);
            } else {
                $resolved[$key] = $value;
            }
        }
        return $resolved;
    }

    /**
     * Check if a parameter has a binding.
     */
    public static function hasBinding(string $param): bool
    {
        return isset(self::$bindings[$param]);
    }

    /**
     * Get all registered bindings.
     */
    public static function getBindings(): array
    {
        return self::$bindings;
    }

    /**
     * Clear all bindings.
     */
    public static function flush(): void
    {
        self::$bindings = [];
    }
}
