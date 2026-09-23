<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Route Cache — compiles all routes to a PHP array for fast dispatch.
 *
 * Usage:
 *   RouteCache::compile();           // generate cache from registered routes
 *   RouteCache::load();              // load cached routes into router
 *   RouteCache::clear();             // remove cache file
 *
 * The Router checks for cache on dispatch and loads it if available.
 */
class RouteCache
{
    protected static string $cachePath = '';

    /**
     * Get the cache file path.
     */
    public static function getPath(): string
    {
        if (self::$cachePath === '') {
            self::$cachePath = dirname(__DIR__) . '/storage/cache/routes.php';
        }
        return self::$cachePath;
    }

    /**
     * Set the cache path (for testing).
     */
    public static function forceCachePath(string $path): void
    {
        self::$cachePath = $path;
    }

    /**
     * Compile registered routes to a cache file.
     */
    public static function compile(Router $router): void
    {
        // Routes are stored in router's $routes property (protected)
        // We use reflection to access them, or the router exposes a method
        $ref = new \ReflectionClass($router);
        $prop = $ref->getProperty('routes');
        $prop->setAccessible(true);
        $routes = $prop->getValue($router);

        $globalMiddleware = [];
        $gmProp = $ref->getProperty('globalMiddleware');
        $gmProp->setAccessible(true);
        $globalMiddleware = $gmProp->getValue($router);

        $data = [
            'routes'        => $routes,
            'global_middleware' => $globalMiddleware,
            'compiled_at'   => time(),
        ];

        $code = "<?php\n/**\n * Auto-generated route cache — do not edit.\n * Regenerate with: php bin/console route:cache\n */\n\nreturn " . var_export($data, true) . ";\n";

        $dir = dirname(self::$cachePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents(self::$cachePath, $code, LOCK_EX);
    }

    /**
     * Check if a valid cache file exists.
     */
    public static function hasCache(): bool
    {
        return is_file(self::$cachePath) && is_readable(self::$cachePath);
    }

    /**
     * Load cached routes into a router.
     */
    public static function load(Router $router): void
    {
        if (!self::hasCache()) {
            return;
        }

        $data = require self::$cachePath;
        if (!is_array($data)) {
            return;
        }

        $ref = new \ReflectionClass($router);

        $routesProp = $ref->getProperty('routes');
        $routesProp->setAccessible(true);
        $routesProp->setValue($router, $data['routes'] ?? []);

        $gmProp = $ref->getProperty('globalMiddleware');
        $gmProp->setAccessible(true);
        $gmProp->setValue($router, $data['global_middleware'] ?? []);
    }

    /**
     * Clear the route cache file.
     */
    public static function clear(): void
    {
        if (is_file(self::$cachePath)) {
            unlink(self::$cachePath);
        }
    }

    /**
     * Get cache compilation time.
     */
    public static function getCompiledAt(): ?int
    {
        if (!self::hasCache()) {
            return null;
        }
        $data = require self::$cachePath;
        return $data['compiled_at'] ?? null;
    }
}

/**
 * View Cache — compiles Blade-like templates to PHP for faster rendering.
 */
class ViewCache
{
    protected static string $cacheDir;

    public static function init(): void
    {
        self::$cacheDir = dirname(__DIR__) . '/storage/cache/views';
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
    }

    /**
     * Compile a view template to cached PHP.
     */
    public static function compile(string $viewPath, string $viewName): string
    {
        self::init();

        // Simple compilation: escape @ directives to PHP
        $content = file_get_contents($viewPath);
        if ($content === false) {
            return '';
        }

        // Transform @foreach, @if, @else, @endif, @verbatim, @endforeach
        $content = preg_replace('/@foreach\s+\(([^)]+)\)\s*as\s*\(([^\)]+)\)/', '<?php foreach($1 as $2): ?>', $content);
        $content = preg_replace('/@endforeach/', '<?php endforeach; ?>', $content);
        $content = preg_replace('/@if\s*\(([^)]+)\)/', '<?php if($1): ?>', $content);
        $content = preg_replace('/@else/', '<?php else: ?>', $content);
        $content = preg_replace('/@endif/', '<?php endif; ?>', $content);
        $content = preg_replace('/@section\s*\(([^)]+)\)/', '<?php ob_start(); ?>', $content);
        $content = preg_replace('/@endslot/', '<?php echo ob_get_clean(); ?>', $content);
        $content = preg_replace('/@extends\s*\(([^)]+)\)/', '<?php $this->layout = $1; ?>', $content);
        $content = preg_replace('/\{\{\{\s*([^}]+)\s*\}\}\}/', '<?php echo e($1); ?>', $content);
        $content = preg_replace('/\{\{\s*([^}]+)\s*\}\}/', '<?php echo $1; ?>', $content);

        $cachePath = self::$cacheDir . '/' . md5($viewName) . '.php';
        file_put_contents($cachePath, '<?php ' . $content, LOCK_EX);
        return $cachePath;
    }

    /**
     * Get cached view path.
     */
    public static function getCachedPath(string $viewName): ?string
    {
        self::init();
        $path = self::$cacheDir . '/' . md5($viewName) . '.php';
        return is_file($path) ? $path : null;
    }

    /**
     * Clear all cached views.
     */
    public static function clear(): void
    {
        self::init();
        foreach (glob(self::$cacheDir . '/*.php') as $file) {
            unlink($file);
        }
    }
}
