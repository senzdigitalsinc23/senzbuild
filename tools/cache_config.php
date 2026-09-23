#!/usr/bin/env php
<?php
/**
 * Config cache generator.
 *
 * Usage:
 *   php tools/cache_config.php            # generate cache
 *   php tools/cache_config.php --clear    # delete cache
 *   php tools/cache_config.php --status   # show cache info
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Core\ConfigCache;

define('BASE_PATH', __DIR__ . '/..');

$options = $argv;
array_shift($options); // remove script name

if (in_array('--help', $options) || in_array('-h', $options)) {
    echo "Config Cache Tool\n\n";
    echo "Usage:\n";
    echo "  php tools/cache_config.php            Generate config cache\n";
    echo "  php tools/cache_config.php --clear    Delete config cache\n";
    echo "  php tools/cache_config.php --status   Show cache status\n";
    echo "  php tools/cache_config.php --verbose  Verbose output\n";
    exit(0);
}

$basePath = BASE_PATH;
$configDir = $basePath . '/config';
$outputDir = $basePath . '/storage/config';

if (in_array('--clear', $options)) {
    ConfigCache::clear($outputDir);
    echo "Config cache cleared.\n";
    exit(0);
}

if (in_array('--status', $options)) {
    $cache = new ConfigCache();
    $hasCache = $cache::hasCache($outputDir);
    $path = $cache::getPath($outputDir);
    echo "Config cache status:\n";
    echo "  Path: {$path}\n";
    echo "  Exists: " . ($hasCache ? 'Yes' : 'No') . "\n";
    if ($hasCache) {
        $mtime = filemtime($path);
        echo "  Last modified: " . date('Y-m-d H:i:s', $mtime) . "\n";
        $size = filesize($path);
        echo "  Size: {$size} bytes\n";
    }
    exit(0);
}

// Default: generate cache
if (!is_dir($configDir)) {
    echo "Error: Config directory not found: {$configDir}\n";
    exit(1);
}

$verbose = in_array('--verbose', $options);

if ($verbose) {
    echo "Scanning config files in: {$configDir}\n";
}

$cache = new ConfigCache();
$path = $cache::cache($configDir, $outputDir);

if ($verbose) {
    echo "Cache written to: {$path}\n";
} else {
    echo "Config cache generated.\n";
}

exit(0);
