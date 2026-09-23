<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Memcached Cache Driver — distributed caching via Memcached.
 *
 * Requires: memcached extension
 * Config: CACHE_DRIVER=memcached in .env
 */
class MemcachedCache implements CacheItemPoolInterface
{
    protected ?\Memcached $memcached = null;
    protected string $prefix = 'fw_';

    public function __construct(array $servers = [])
    {
        $this->memcached = new \Memcached();
        $hosts = $servers ?: [
            ['127.0.0.1', 11211, 1],
        ];

        foreach ($hosts as $host) {
            $this->memcached->addServer($host[0], $host[1], $host[2]);
        }
    }

    // ── PSR-16 CacheItemPoolInterface ─────────────────────────────────────

    public function getItem(string $key): CacheItemInterface
    {
        $value = $this->get($key);
        $expired = !$this->hasItem($key);
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
        $val = $this->memcached->get($this->prefix . $key);
        return $val !== false && $val !== null;
    }

    public function clear(): bool
    {
        return $this->memcached->flush();
    }

    public function deleteItem(string $key): bool
    {
        $this->memcached->delete($this->prefix . $key);
        return true;
    }

    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->deleteItem($key);
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
        return $this->save($item);
    }

    public function commit(): bool
    {
        return true;
    }

    // ── Legacy API ───────────────────────────────────────────────────────

    public function set(string $key, mixed $value, int $ttl = 3600): void
    {
        $this->memcached->set($this->prefix . $key, serialize($value), $ttl);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $val = $this->memcached->get($this->prefix . $key);
        return $val !== false ? unserialize($val) : $default;
    }

    public function forget(string $key): void
    {
        $this->deleteItem($key);
    }

    public function tag(string $tag): CacheTagProxy
    {
        return new CacheTagProxy($this, $tag);
    }

    public function lock(string $name, int $ttl = 10, ?callable $callback = null, mixed $default = null): mixed
    {
        $lockKey = "__lock:{$name}";
        $acquired = $this->memcached->add($lockKey, '1', $ttl);
        if (!$acquired) {
            return $default !== null ? $default : false;
        }
        try {
            return $callback ? $callback() : null;
        } finally {
            $this->memcached->delete($lockKey);
        }
    }
}
