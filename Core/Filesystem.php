<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Flysystem-like Storage Abstraction — unified file storage interface.
 *
 * Supports: local, S3, SFTP
 *
 * Usage:
 *   Filesystem::extend('local', new LocalFilesystemAdapter(__DIR__ . '/storage/app'));
 *   Filesystem::write('local', 'files/photo.jpg', $contents);
 *   $stream = Filesystem::readStream('local', 'files/photo.jpg');
 *   Filesystem::delete('local', 'files/photo.jpg');
 *   $url = Filesystem::publicUrl('local', 'files/photo.jpg');
 */
class Filesystem
{
    /** @var array<string, array{adapter: object, config: array}> */
    protected static array $disks = [];

    /**
     * Register a filesystem disk.
     */
    public static function extend(string $name, object $adapter, array $config = []): void
    {
        self::$disks[$name] = ['adapter' => $adapter, 'config' => $config];
    }

    /**
     * Get a filesystem instance for a disk.
     */
    public static function disk(string $name = 'local'): object
    {
        if (!isset(self::$disks[$name])) {
            throw new \InvalidArgumentException("Filesystem disk '{$name}' not registered");
        }
        return self::$disks[$name]['adapter'];
    }

    /**
     * Check if a disk exists.
     */
    public static function hasDisk(string $name): bool
    {
        return isset(self::$disks[$name]);
    }

    /**
     * List all registered disks.
     */
    public static function disks(): array
    {
        return array_keys(self::$disks);
    }

    /**
     * Get the root path of a disk.
     */
    public static function path(string $disk, string $path = ''): string
    {
        $config = self::$disks[$disk]['config'] ?? [];
        $root = $config['root'] ?? '/';
        return rtrim($root, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Read a file's contents.
     */
    public static function read(string $disk, string $path): string|false
    {
        $adapter = self::disk($disk);
        if (method_exists($adapter, 'read')) {
            return $adapter->read($path);
        }
        return @file_get_contents(self::path($disk, $path));
    }

    /**
     * Write file contents.
     */
    public static function write(string $disk, string $path, string $contents): bool
    {
        $adapter = self::disk($disk);
        if (method_exists($adapter, 'write')) {
            return $adapter->write($path, $contents);
        }
        $fullPath = self::path($disk, $path);
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return file_put_contents($fullPath, $contents) !== false;
    }

    /**
     * Get a file stream for reading.
     */
    public static function readStream(string $disk, string $path)
    {
        $adapter = self::disk($disk);
        if (method_exists($adapter, 'readStream')) {
            return $adapter->readStream($path);
        }
        return fopen(self::path($disk, $path), 'rb');
    }

    /**
     * Stream write to a file.
     */
    public static function writeStream(string $disk, string $path, mixed $stream): bool
    {
        $adapter = self::disk($disk);
        if (method_exists($adapter, 'writeStream')) {
            return $adapter->writeStream($path, $stream);
        }
        $contents = stream_get_contents($stream);
        return self::write($disk, $path, $contents);
    }

    /**
     * Delete a file.
     */
    public static function delete(string $disk, string $path): bool
    {
        $adapter = self::disk($disk);
        if (method_exists($adapter, 'delete')) {
            return $adapter->delete($path);
        }
        return @unlink(self::path($disk, $path));
    }

    /**
     * Check if a file exists.
     */
    public static function exists(string $disk, string $path): bool
    {
        $adapter = self::disk($disk);
        if (method_exists($adapter, 'has')) {
            return $adapter->has($path);
        }
        return file_exists(self::path($disk, $path));
    }

    /**
     * Get file size.
     */
    public static function size(string $disk, string $path): int
    {
        $adapter = self::disk($disk);
        if (method_exists($adapter, 'size')) {
            return $adapter->size($path);
        }
        return (int) filesize(self::path($disk, $path));
    }

    /**
     * Get last modified timestamp.
     */
    public static function lastModified(string $disk, string $path): int
    {
        $adapter = self::disk($disk);
        if (method_exists($adapter, 'lastModified')) {
            return $adapter->lastModified($path);
        }
        return (int) filemtime(self::path($disk, $path));
    }

    /**
     * Get a public URL for a file.
     */
    public static function publicUrl(string $disk, string $path): string
    {
        $adapter = self::disk($disk);
        if (method_exists($adapter, 'publicUrl')) {
            return $adapter->publicUrl($path);
        }
        $config = self::$disks[$disk]['config'] ?? [];
        $baseUrl = $config['url'] ?? Config::get('app.url', 'http://localhost') . '/storage';
        return $baseUrl . '/' . ltrim($path, '/');
    }

    /**
     * List directory contents.
     */
    public static function listContents(string $disk, string $path = ''): array
    {
        $adapter = self::disk($disk);
        if (method_exists($adapter, 'listContents')) {
            $result = $adapter->listContents($path);
            return is_iterable($result) ? iterator_to_array($result) : [];
        }
        $fullPath = self::path($disk, $path);
        if (!is_dir($fullPath)) {
            return [];
        }
        $results = [];
        foreach (scandir($fullPath) as $item) {
            if ($item === '.' || $item === '..') continue;
            $results[] = [
                'type' => is_dir("{$fullPath}/{$item}") ? 'dir' : 'file',
                'path' => $item,
            ];
        }
        return $results;
    }

    /**
     * Copy a file.
     */
    public static function copy(string $fromDisk, string $fromPath, string $toDisk, string $toPath): bool
    {
        $content = self::read($fromDisk, $fromPath);
        if ($content === false) {
            return false;
        }
        return self::write($toDisk, $toPath, $content);
    }

    /**
     * Move a file.
     */
    public static function move(string $fromDisk, string $fromPath, string $toDisk, string $toPath): bool
    {
        $moved = self::copy($fromDisk, $fromPath, $toDisk, $toPath);
        if ($moved) {
            self::delete($fromDisk, $fromPath);
        }
        return $moved;
    }
}

/**
 * Adapter for local filesystem operations.
 */
class LocalFilesystemAdapter
{
    protected string $root;

    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/');
    }

    public function read(string $path): string|false
    {
        return file_get_contents("{$this->root}/{$path}");
    }

    public function write(string $path, string $contents): bool
    {
        $fullPath = "{$this->root}/{$path}";
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return file_put_contents($fullPath, $contents) !== false;
    }

    public function delete(string $path): bool
    {
        return @unlink("{$this->root}/{$path}");
    }

    public function has(string $path): bool
    {
        return file_exists("{$this->root}/{$path}");
    }

    public function size(string $path): int
    {
        return (int) filesize("{$this->root}/{$path}");
    }

    public function lastModified(string $path): int
    {
        return (int) filemtime("{$this->root}/{$path}");
    }

    public function publicUrl(string $path): string
    {
        try {
            $baseUrl = Config::get('app.url', 'http://localhost');
        } catch (\Exception $e) {
            $baseUrl = 'http://localhost';
        }
        return $baseUrl . '/storage/' . $path;
    }

    /**
     * @return \Generator|array<int, array{type: string, path: string}>
     */
    public function listContents(string $path = ''): iterable
    {
        $fullPath = "{$this->root}/{$path}";
        if (!is_dir($fullPath)) {
            return [];
        }
        $results = [];
        foreach (scandir($fullPath) as $item) {
            if ($item === '.' || $item === '..') continue;
            $relativePath = $path === '' ? $item : "{$path}/{$item}";
            $results[] = [
                'type' => is_dir("{$fullPath}/{$item}") ? 'dir' : 'file',
                'path' => $relativePath,
            ];
        }
        return $results;
    }
}
