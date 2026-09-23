<?php
declare(strict_types=1);

namespace Tests\Factory;

class CacheFake
{
    protected static array $store = [];
    protected static array $tags = [];

    public static function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        self::$store[$key] = ['value' => $value, 'ttl' => $ttl, 'expires' => $ttl ? time() + $ttl : null];
        return true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (!isset(self::$store[$key])) {
            return $default;
        }
        $entry = self::$store[$key];
        if ($entry['expires'] !== null && time() > $entry['expires']) {
            unset(self::$store[$key]);
            return $default;
        }
        return $entry['value'];
    }

    public static function has(string $key): bool
    {
        return isset(self::$store[$key]);
    }

    public static function forget(string $key): bool
    {
        if (isset(self::$store[$key])) {
            unset(self::$store[$key]);
            return true;
        }
        return false;
    }

    public static function flush(): bool
    {
        self::$store = [];
        return true;
    }

    public static function clear(): void
    {
        self::$store = [];
    }

    public static function tag(string $name): TaggedCacheFake
    {
        return new TaggedCacheFake($name);
    }
}

class TaggedCacheFake
{
    protected string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        CacheFake::put("{$this->name}:{$key}", $value, $ttl);
        return true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return CacheFake::get("{$this->name}:{$key}", $default);
    }

    public function forget(string $key): bool
    {
        return CacheFake::forget("{$this->name}:{$key}");
    }
}
