<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Health & Readiness Service — Kubernetes-compatible health checks.
 *
 * Endpoints:
 *   GET /api/v1/health   — liveness probe (is the app alive?)
 *   GET /api/v1/readiness — readiness probe (is the app ready to serve?)
 */
class HealthService
{
    /**
     * Get liveness status (basic alive check).
     */
    public static function liveness(): array
    {
        return [
            'status'  => 'ok',
            'checks'  => [
                'php' => [
                    'status' => 'ok',
                    'version' => PHP_VERSION,
                ],
            ],
            'timestamp' => time(),
        ];
    }

    /**
     * Get readiness status (dependency checks).
     */
    public static function readiness(): array
    {
        $checks = [
            'php' => [
                'status' => 'ok',
                'version' => PHP_VERSION,
            ],
        ];

        // Database
        try {
            $db = Database::getInstance()->getConnection();
            $db->query('SELECT 1');
            $checks['database'] = ['status' => 'ok'];
        } catch (\Throwable $e) {
            $checks['database'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        // Cache
        try {
            $cache = new Cache();
            $cache->set('__readiness_test__', 'ok', 1);
            $val = $cache->get('__readiness_test__');
            $checks['cache'] = $val === 'ok' ? ['status' => 'ok'] : ['status' => 'error', 'message' => 'Cache write/read failed'];
        } catch (\Throwable $e) {
            $checks['cache'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        // Queue
        try {
            $queue = new Queue(new Logger(dirname(__DIR__) . '/storage/logs/health.log'));
            $checks['queue'] = ['status' => 'ok'];
        } catch (\Throwable $e) {
            $checks['queue'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        // Maintenance mode
        if (MaintenanceMode::enabled()) {
            $checks['maintenance'] = ['status' => 'degraded', 'message' => 'Maintenance mode active'];
        }

        $overall = 'ok';
        foreach ($checks as $check) {
            if ($check['status'] === 'error') {
                $overall = 'error';
                break;
            }
            if ($check['status'] === 'degraded') {
                $overall = 'degraded';
            }
        }

        return [
            'status'    => $overall,
            'checks'    => $checks,
            'timestamp' => time(),
        ];
    }

    /**
     * Get ping (fastest possible check).
     */
    public static function ping(): array
    {
        return ['status' => 'ok', 'timestamp' => time(), 'uptime' => function_exists('getmypid') ? time() - $_SERVER['REQUEST_TIME'] : 0];
    }
}
