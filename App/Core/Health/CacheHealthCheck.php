<?php
declare(strict_types=1);

namespace App\Core\Health;

use App\Core\Cache;

class CacheHealthCheck implements HealthCheckInterface
{
    private Cache $cache;

    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }

    public function getName(): string
    {
        return 'cache';
    }

    public function check(): array
    {
        try {
            $key = '__health_check_' . time();
            $this->cache->set($key, 'ok', 10);
            $value = $this->cache->get($key);
            $this->cache->forget($key);

            if ($value === 'ok') {
                return [
                    'status'  => 'healthy',
                    'message' => 'Cache is working correctly',
                ];
            }

            return [
                'status'  => 'warning',
                'message' => 'Cache read/write mismatch',
            ];
        } catch (\Throwable $e) {
            return [
                'status'  => 'unhealthy',
                'message' => 'Cache check failed: ' . $e->getMessage(),
            ];
        }
    }
}
