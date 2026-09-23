<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Array Cache Driver — in-memory cache for testing and ephemeral use.
 *
 * Usage: Set `CACHE_DRIVER=array` in .env
 */
class ArrayCache implements CacheItemPoolInterface
{
    protected static array $store = [];
    protected static array $tags = [];

    public function getItem(string $key): CacheItemInterface
    {
        $value = $this->get($key);
        $expired = !isset(self::$store[$key]) || (self::$store[$key]['expires'] ?? 0) < time();
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

    public function clear(): bool
    {
        self::$store = [];
        self::$tags = [];
        return true;
    }

    public function deleteItem(string $key): bool
    {
        unset(self::$store[$key]);
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
        self::$store[$key] = [
            'value'   => $value,
            'expires' => time() + $ttl,
            'tags'    => [],
        ];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!isset(self::$store[$key])) {
            return $default;
        }
        if ($this->isExpired($key)) {
            unset(self::$store[$key]);
            return $default;
        }
        return self::$store[$key]['value'];
    }

    public function forget(string $key): void
    {
        $this->deleteItem($key);
    }

    public function tag(string $tag): CacheTagProxy
    {
        return new CacheTagProxy($this, $tag);
    }

    // ── Internal ─────────────────────────────────────────────────────────

    protected function isExpired(string $key): bool
    {
        return isset(self::$store[$key]) && (self::$store[$key]['expires'] ?? 0) < time();
    }

    /**
     * Clear all stored data (for testing).
     */
    public static function flush(): void
    {
        self::$store = [];
        self::$tags = [];
    }
}
