<?php
declare(strict_types=1);

namespace App\Interfaces;

/**
 * File storage abstraction — swap drivers without touching business logic.
 *
 * Implementations:
 *   LocalFileStorage  — writes to the filesystem (default for dev)
 *   S3Storage         — writes to Amazon S3 or any S3-compatible service
 *
 * Usage:
 *   $storage = app()->make(FileStorage::class);   // resolved via DI
 *   $storage->put('photos/avatar.png', $contents, ['mime' => 'image/png']);
 *   $url      = $storage->url('photos/avatar.png');
 *   $exists   = $storage->exists('photos/avatar.png');
 *   $storage->delete('photos/avatar.png');
 */
interface FileStorage
{
    /**
     * Upload a file.
     *
     * @param string $path    Relative path (e.g. 'photos/avatar.png')
     * @param string $contents Raw file contents
     * @param array  $options Optional metadata: 'mime', 'public' (bool)
     * @return array  ['path' => string, 'size' => int, 'mime' => string, 'url' => string]
     */
    public function put(string $path, string $contents, array $options = []): array;

    /**
     * Read file contents.
     *
     * @param string $path Relative path
     * @return string|false File contents or false if not found
     */
    public function get(string $path): string|false;

    /**
     * Check if a file exists.
     */
    public function exists(string $path): bool;

    /**
     * Delete a file.
     *
     * @return bool True if deleted (or never existed)
     */
    public function delete(string $path): bool;

    /**
     * Get a publicly accessible URL for a file.
     *
     * @param string $path       Relative path
     * @param int    $expireSeconds URL expiration in seconds (only for signed URLs)
     * @return string URL
     */
    public function url(string $path, int $expireSeconds = 0): string;

    /**
     * Get the file size in bytes.
     *
     * @param string $path Relative path
     * @return int|false Size in bytes, or false if not found
     */
    public function size(string $path): int|false;
}
