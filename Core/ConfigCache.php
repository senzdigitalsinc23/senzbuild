<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Cache-busted config loader.
 *
 * On first run, ConfigCache::cache() scans all config/*.php files,
 * merges them into a single PHP array, and writes it to
 * storage/config/cache.php.
 *
 * On subsequent requests, Config::load() checks for the cached file first
 * and skips all individual file inclusions — dramatically faster in production.
 */
class ConfigCache
{
    protected static string $cachePath = '';
    protected static ?string $basePath = null;

    /**
     * Set the base path for config/cache resolution.
     * Call this before any cache operations when running from a scaffolded project.
     */
    public static function setBasePath(string $path): void
    {
        self::$basePath = rtrim($path, '/\\');
    }

    /**
     * Generate the config cache file from all config/*.php files.
     *
     * @param string|null $configDir Override default config directory
     * @param string|null $outputDir Override default cache output directory
     * @return string Path to the generated cache file
     */
    public static function cache(?string $configDir = null, ?string $outputDir = null): string
    {
        $basePath = self::$basePath ?? dirname(__DIR__);
        $configDir ??= $basePath . '/config';
        $outputDir ??= $basePath . '/storage/config';

        self::$cachePath = $outputDir . '/cache.php';

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $files = glob(rtrim($configDir, '/') . '/*.php');
        sort($files);

        $merged = [];
        foreach ($files as $file) {
            $key = basename($file, '.php');
            $config = require $file;
            if (is_array($config)) {
                $merged[$key] = $config;
            }
        }

        $code = "<?php\n"
            . "/**\n"
            . " * Auto-generated config cache — do not edit manually.\n"
            . " * Regenerate with: php tools/cache_config.php\n"
            . " * Generated at: " . date('Y-m-d H:i:s') . "\n"
            . " */\n\n"
            . "return " . var_export($merged, true) . ";\n";

        file_put_contents(self::$cachePath, $code, LOCK_EX);
        chmod(self::$cachePath, 0644);

        return self::$cachePath;
    }

    /**
     * Check whether a valid cached config file exists.
     */
    public static function hasCache(?string $outputDir = null): bool
    {
        $basePath = self::$basePath ?? dirname(__DIR__);
        $outputDir ??= $basePath . '/storage/config';
        $path = $outputDir . '/cache.php';

        return is_file($path) && is_readable($path);
    }

    /**
     * Delete the cached config file.
     */
    public static function clear(?string $outputDir = null): void
    {
        $basePath = self::$basePath ?? dirname(__DIR__);
        $outputDir ??= $basePath . '/storage/config';
        $path = $outputDir . '/cache.php';

        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Return the absolute path to the cache file.
     */
    public static function getPath(?string $outputDir = null): string
    {
        $basePath = self::$basePath ?? dirname(__DIR__);
        $outputDir ??= $basePath . '/storage/config';
        return $outputDir . '/cache.php';
    }
}
