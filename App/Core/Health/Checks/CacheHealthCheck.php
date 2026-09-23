<?php
declare(strict_types=1);

namespace App\Core\Health\Checks;

use App\Core\Health\HealthCheckInterface;
use App\Core\Cache;

class CacheHealthCheck implements HealthCheckInterface
{
    public function __construct(private Cache $cache) {}

    public function getName(): string
    {
        return 'cache';
    }

    public function check(): array
    {
        try {
            $testKey = 'health_check_' . time();
            $testValue = 'ok';

            $this->cache->set($testKey, $testValue, 10);
            $value = $this->cache->get($testKey);
            $this->cache->forget($testKey);

            if ($value === $testValue) {
                return [
                    'status' => 'healthy',
                    'message' => 'Cache is working correctly',
                ];
            }

            return [
                'status' => 'warning',
                'message' => 'Cache read/write mismatch',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'warning',
                'message' => 'Cache check failed (non-critical)',
                'error' => $e->getMessage(),
            ];
        }
    }
}
