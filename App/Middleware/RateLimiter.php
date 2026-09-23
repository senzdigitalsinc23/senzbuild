<?php
declare(strict_types=1);
// app/Middleware/RateLimiterMiddleware.php
namespace App\Middleware;

class RateLimiter
{
    private int $maxRequests;
    private int $perSeconds;
    private string $prefix;
    private int $maxViolations = 5;
    private int $banDuration = 3600; // 1 hour
    private \App\Core\Cache $cache;

    public function __construct()
    {
        $this->cache = new \App\Core\Cache();
        $this->maxRequests = (int)($_ENV['RATE_LIMIT_MAX'] ?? 1000); // requests
        $this->perSeconds  = (int)($_ENV['RATE_LIMIT_WINDOW'] ?? 1000); // seconds
        $this->prefix      = 'ratelimit_';
    }

    public function handle($request = null, $response = null, $next = null)
    {
        $ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        // Sanitize IP for filename
        $safeIp = str_replace([':', '.'], '_', $ip);

        $path = $_SERVER['REQUEST_METHOD'] . ':' . ($_SERVER['REQUEST_URI'] ?? '/');

        // Tiered Limit Resolution
        $limit = $this->resolveLimit();
        $key  = $this->prefix . hash('sha256', $ip . '|' . $path);

        $banKey = 'ban_' . $safeIp;
        $violationKey = 'violation_' . $safeIp;

        // 1. Check if Banned
        $banData = $this->cache->get($banKey);
        if ($banData) {
            $retryAfter = $banData['expires_at'] - time();
            if ($retryAfter > 0) {
                return $this->response429($response, $retryAfter, 'Access temporarily suspended due to repeated rate limit violations.');
            }
        }

        $now = time();
        $bucket = $this->cache->get($key) ?? [];

        // Clean old
        $bucket = array_filter($bucket, fn($t) => ($now - $t) < $this->perSeconds);

        if (count($bucket) >= $limit) {
            // Count violation
            $violations = $this->cache->get($violationKey) ?? ['count' => 0, 'expires_at' => 0];

            // If violation record is old/expired, reset
            if ($violations['expires_at'] < $now) {
                $violations = ['count' => 0, 'expires_at' => $now + ($this->perSeconds * 10)]; // memory for 10x window
            }

            $violations['count']++;
            $violations['expires_at'] = $now + ($this->perSeconds * 10);
            $this->cache->set($violationKey, $violations, 600);

            // Check if processed into Ban
            if ($violations['count'] >= $this->maxViolations) {
                // BAN THEM
                $this->cache->set($banKey, ['expires_at' => $now + $this->banDuration], $this->banDuration);
                return $this->response429($response, $this->banDuration, 'Access temporarily suspended due to repeated rate limit violations.');
            }

            return $this->response429($response, $this->perSeconds);
        }

        $bucket[] = $now;
        $this->cache->set($key, $bucket, $this->perSeconds);
        if ($next) return $next($request, $response);
        return $response;
    }

    private function resolveLimit(): int
    {
        $user = \App\Core\Session::get('user');
        $role = $user['role'] ?? 'default';

        $limits = [
            'admin'    => 1000,
            'manager'  => 500,
            'student'  => 100,
            'default'  => 100,
        ];

        return $limits[$role] ?? $limits['default'];
    }



    private function response429($response, $retryAfter, $message = 'Too Many Requests')
    {
        $resp = $response ?? new \App\Core\Response();
        $resp->setStatusCode(429);
        $resp->setHeader('Retry-After', max(1, $retryAfter));
        $resp->setHeader('Content-Type', 'application/json');
        $resp->setContent(json_encode([
            'success' => false,
            'code' => 429,
            'message' => $message]));
        return $resp;
    }
}
