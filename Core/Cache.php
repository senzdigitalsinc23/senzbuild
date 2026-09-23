<?php
declare(strict_types=1);

namespace App\Core;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * PSR-16 compliant cache implementation.
 *
 * Wraps the existing App\Core\Cache with PSR-16's CacheItemPoolInterface.
 * All existing methods remain available for backward compatibility.
 */
class Cache implements CacheItemPoolInterface
{
    protected string $path;
    protected ?object $redis = null;
    protected bool $useRedis;
    protected array $tags = [];

    /** @var CacheItemInterface[] Deferred items waiting to be committed */
    protected array $deferredItems = [];

    public function __construct(string $path = __DIR__ . '/../storage/cache')
    {
        $this->path = $path;
        $this->useRedis = false;

        if (!is_dir($this->path)) {
            mkdir($this->path, 0777, true);
        }

        $host = $_ENV['REDIS_HOST'] ?? '';
        $port = (int)($_ENV['REDIS_PORT'] ?? 6379);
        if (!empty($host) && extension_loaded('redis')) {
            try {
                $this->redis = new \Redis();
                $this->redis->connect($host, $port, 2.5);
                $this->useRedis = true;
            } catch (\Throwable) {
                // Fall back to file cache
            }
        }
    }

    // ── PSR-16 CacheItemPoolInterface ───────────────────────────────────────

    public function getItem(string $key): CacheItemInterface
    {
        $value = $this->get($key);
        $expired = $this->isExpired($key);
        return new CacheItem($key, $value, $expired, $this);
    }

    public function getItems(array $keys = []): iterable
    {
        $items = [];
        foreach ($keys as $key) {
            $items[$key] = $this->getItem($key);
        }
        return $items;
    }

    public function hasItem(string $key): bool
    {
        return $this->get($key) !== null && !$this->isExpired($key);
    }

    /**
     * Legacy alias for hasItem().
     */
    public function has(string $key): bool
    {
        return $this->hasItem($key);
    }

    public function clear(): bool
    {
        // Delete all cache files
        $dir = $this->path;
        if (is_dir($dir)) {
            foreach (scandir($dir) as $file) {
                if ($file === '.' || $file === '..') continue;
                $path = $dir . DIRECTORY_SEPARATOR . $file;
                if (is_file($path) && pathinfo($file, PATHINFO_EXTENSION) === 'cache') {
                    unlink($path);
                }
            }
        }
        return true;
    }

    /**
     * Legacy alias for deleteItem().
     */
    public function forget(string $key): void
    {
        $this->removeFile($key);
    }

    public function deleteItem(string $key): bool
    {
        $this->removeFile($key);
        return true;
    }

    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->forget($key);
        }
        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        if (!$item instanceof CacheItem) {
            return false;
        }
        $this->set($item->getKey(), $item->get(), $item->getTtl() ?? 3600);
        return true;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        if (!$item instanceof CacheItem) {
            return false;
        }
        $this->deferredItems[$item->getKey()] = $item;
        return true;
    }

    public function commit(): bool
    {
        $success = true;
        foreach ($this->deferredItems as $key => $item) {
            if (!$this->save($item)) {
                $success = false;
            }
        }
        $this->deferredItems = [];
        return $success;
    }

    // ── Legacy / Extended API (backward compatible) ────────────────────────

    public function tag(string $tag): CacheTagProxy
    {
        return new CacheTagProxy($this, $tag);
    }

    public function forgetTag(string $tag): void
    {
        if ($this->useRedis) {
            $members = $this->redis->sMembers("tags:{$tag}");
            foreach ($members as $key) {
                $this->redis->del($key);
            }
            $this->redis->del("tags:{$tag}");
            return;
        }

        $prefix = "tag:{$tag}|";
        $dir = $this->path;
        foreach (glob("{$dir}/*.cache") as $file) {
            $content = file_get_contents($file);
            if ($content === false) continue;
            $data = @unserialize($content);
            if (!is_array($data) || !isset($data['tags']) || !in_array($tag, $data['tags'], true)) continue;
            unlink($file);
        }
    }

    public function flushTags(): void
    {
        if ($this->useRedis) {
            $keys = $this->redis->keys('tags:*');
            foreach ($keys as $tagKey) {
                $members = $this->redis->sMembers($tagKey);
                foreach ($members as $key) {
                    $this->redis->del($key);
                }
                $this->redis->del($tagKey);
            }
            return;
        }

        $dir = $this->path;
        foreach (glob("{$dir}/*.cache") as $file) {
            $content = file_get_contents($file);
            if ($content === false) continue;
            $data = @unserialize($content);
            if (!is_array($data) || !isset($data['tags']) || empty($data['tags'])) continue;
            unlink($file);
        }
    }

    public function lock(string $name, int $ttl = 10, ?callable $callback = null, mixed $default = null): mixed
    {
        $lockKey = "__lock:{$name}";

        if ($this->useRedis) {
            $acquired = $this->redis->set($lockKey, '1', ['nx', 'ex' => $ttl]);
            if (!$acquired) {
                return $default !== null ? $default : false;
            }
            try {
                return $callback ? $callback() : null;
            } finally {
                $this->redis->del($lockKey);
            }
        }

        $lockFile = "{$this->path}/.lock_{$name}.lock";
        $attempts = 0;
        $maxAttempts = 10;
        while ($attempts < $maxAttempts) {
            if (@file_put_contents($lockFile, getmypid() . ':' . time(), LOCK_EX | LOCK_NB)) {
                register_shutdown_function(fn() => @unlink($lockFile));
                try {
                    return $callback ? $callback() : null;
                } finally {
                    @unlink($lockFile);
                }
            }
            usleep(100000);
            $attempts++;
        }
        return $default !== null ? $default : false;
    }

    public function set(string $key, mixed $value, int $ttl = 3600): void
    {
        if ($this->useRedis) {
            $this->redis->setex($key, $ttl, serialize($value));
            return;
        }

        $data = [
            'expires_at' => time() + $ttl,
            'value'      => $value,
            'tags'       => $this->tags ?? [],
        ];
        file_put_contents($this->getFile($key), serialize($data));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->useRedis) {
            $val = $this->redis->get($key);
            return $val !== false ? unserialize($val) : $default;
        }

        $file = $this->getFile($key);
        if (!file_exists($file)) {
            return $default;
        }

        $data = unserialize(file_get_contents($file));
        if ($this->isExpired($key)) {
            unlink($file);
            return $default;
        }

        return $data['value'];
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $cached = $this->get($key);
        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }

    // ── Internal helpers ────────────────────────────────────────────────────

    /**
     * Remove a cache file by key.
     */
    protected function removeFile(string $key): void
    {
        if ($this->useRedis) {
            $this->redis->del($key);
            return;
        }
        $file = $this->getFile($key);
        if (file_exists($file)) {
            unlink($file);
        }
    }

    protected function getFile(string $key): string
    {
        return $this->path . '/' . md5($key) . '.cache';
    }

    protected function isExpired(string $key): bool
    {
        if ($this->useRedis) {
            return false; // Redis handles expiration
        }
        $file = $this->getFile($key);
        if (!file_exists($file)) {
            return true;
        }
        $data = unserialize(file_get_contents($file));
        return $data['expires_at'] < time();
    }
}
