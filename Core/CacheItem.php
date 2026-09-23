<?php
declare(strict_types=1);

namespace App\Core;

use Psr\Cache\CacheItemInterface;

/**
 * PSR-16 CacheItem implementation.
 */
class CacheItem implements CacheItemInterface
{
    private string $key;
    private mixed $value;
    private bool $isHit;
    private ?int $ttl;
    private readonly Cache $pool;

    public function __construct(string $key, mixed $value, bool $isHit, Cache $pool)
    {
        $this->key = $key;
        $this->value = $value;
        $this->isHit = $isHit;
        $this->ttl = null;
        $this->pool = $pool;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        return $this->isHit;
    }

    public function expiresAt(?int $timestamp): CacheItemInterface
    {
        if ($timestamp === null) {
            $this->ttl = null;
        } else {
            $this->ttl = $timestamp - time();
        }
        return $this;
    }

    public function expiresAfter(mixed $time): CacheItemInterface
    {
        if ($time instanceof \DateInterval) {
            $this->ttl = (int)(new \DateTimeImmutable())->add($time)->getTimestamp() - time();
        } elseif (is_int($time) || is_numeric($time)) {
            $this->ttl = (int)$time;
        } else {
            $this->ttl = null;
        }
        return $this;
    }

    public function getTtl(): ?int
    {
        return $this->ttl;
    }

    public function set(mixed $value): void
    {
        $this->value = $value;
        $this->isHit = true;
    }
}
