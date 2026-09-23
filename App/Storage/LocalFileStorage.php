<?php
declare(strict_types=1);

namespace App\Storage;

use App\Interfaces\FileStorage;

/**
 * Local filesystem implementation of FileStorage.
 *
 * Files are stored under the directory specified by STORAGE_PATH env
 * (default: __DIR__ . '/../../storage/uploads').
 */
class LocalFileStorage implements FileStorage
{
    protected string $root;
    protected array $mimes;

    public function __construct(?string $root = null)
    {
        $this->root = $root ?? realpath($_ENV['STORAGE_PATH'] ?? __DIR__ . '/../../storage/uploads') ?: __DIR__ . '/../../storage/uploads';
        $this->mimes = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'svg'  => 'image/svg+xml',
            'pdf'  => 'application/pdf',
            'txt'  => 'text/plain',
            'csv'  => 'text/csv',
            'json' => 'application/json',
            'zip'  => 'application/zip',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'mp4'  => 'video/mp4',
            'mp3'  => 'audio/mpeg',
        ];
    }

    public function put(string $path, string $contents, array $options = []): array
    {
        $fullPath = $this->resolvePath($path);
        $dir      = dirname($fullPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($fullPath, $contents);
        $size    = strlen($contents);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime    = $options['mime'] ?? ($this->mimes[$extension] ?? 'application/octet-stream');

        return [
            'path' => $path,
            'size' => $size,
            'mime' => $mime,
            'url'  => $this->url($path),
        ];
    }

    public function get(string $path): string|false
    {
        $fullPath = $this->resolvePath($path);
        return file_exists($fullPath) ? file_get_contents($fullPath) : false;
    }

    public function exists(string $path): bool
    {
        return file_exists($this->resolvePath($path));
    }

    public function delete(string $path): bool
    {
        $fullPath = $this->resolvePath($path);
        if (!file_exists($fullPath)) {
            return true;
        }
        return unlink($fullPath);
    }

    public function url(string $path, int $expireSeconds = 0): string
    {
        // Local URLs are relative to the web root
        $relative = ltrim($path, '/');
        return '/storage/' . $relative;
    }

    public function size(string $path): int|false
    {
        $fullPath = $this->resolvePath($path);
        return file_exists($fullPath) ? filesize($fullPath) : false;
    }

    /**
     * Resolve a relative path against the storage root.
     */
    protected function resolvePath(string $path): string
    {
        // Prevent directory traversal
        $clean = ltrim($path, '/');
        if (str_contains($clean, '..')) {
            throw new \InvalidArgumentException("Invalid path: {$path}");
        }
        return $this->root . '/' . $clean;
    }
}
