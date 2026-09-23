<?php
declare(strict_types=1);

namespace App\Core;

/**
 * File Storage facade — resolves the correct driver via DI container.
 *
 * Usage:
 *   Storage::put('photos/photo.jpg', $contents);
 *   $url = Storage::url('photos/photo.jpg');
 *   Storage::delete('photos/photo.jpg');
 */
class Storage
{
    /**
     * Get the resolved FileStorage instance from the container (or create default).
     */
    public static function drive(): \App\Interfaces\FileStorage
    {
        // Try to resolve from global container if available
        static $driver = null;
        if ($driver !== null) {
            return $driver;
        }

        $disk = $_ENV['FILESYSTEM_DISK'] ?? 'local';

        if ($disk === 's3' && class_exists(\GuzzleHttp\Client::class)) {
            $driver = new \App\Storage\S3Storage(
                client:   new \GuzzleHttp\Client(),
                bucket:   $_ENV['S3_BUCKET'] ?? '',
                region:   $_ENV['S3_REGION'] ?? 'us-east-1',
                baseUrl:  $_ENV['S3_URL'] ?? null,
                acl:      $_ENV['S3_ACL'] ?? null,
            );
        } else {
            $driver = new \App\Storage\LocalFileStorage();
        }

        return $driver;
    }

    public static function put(string $path, string $contents, array $options = []): array
    {
        return static::drive()->put($path, $contents, $options);
    }

    public static function get(string $path): string|false
    {
        return static::drive()->get($path);
    }

    public static function exists(string $path): bool
    {
        return static::drive()->exists($path);
    }

    public static function delete(string $path): bool
    {
        return static::drive()->delete($path);
    }

    public static function url(string $path, int $expireSeconds = 0): string
    {
        return static::drive()->url($path, $expireSeconds);
    }

    public static function size(string $path): int|false
    {
        return static::drive()->size($path);
    }
}
