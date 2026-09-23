<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;

/**
 * Middleware that adds rate limit headers to every response.
 *
 * Headers:
 *   X-RateLimit-Limit   — max requests allowed in the window
 *   X-RateLimit-Remaining — requests remaining in current window
 *   X-RateLimit-Reset   — Unix timestamp when the window resets
 */
class RateLimitHeadersMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Response $response, callable $next): Response
    {
        $limit   = (int)($_ENV['RATE_LIMIT_MAX'] ?? 60);
        $window  = (int)($_ENV['RATE_LIMIT_WINDOW'] ?? 60);
        $remaining = $this->getRemaining($request, $limit, $window);

        $response->setHeader('X-RateLimit-Limit', (string)$limit);
        $response->setHeader('X-RateLimit-Remaining', (string)max(0, $remaining));
        $response->setHeader('X-RateLimit-Reset', (string)(time() + $window));

        return $next($request, $response);
    }

    private function getRemaining(Request $request, int $limit, int $window): int
    {
        // Simple in-memory counter per IP (for single-instance deployments)
        static $counts = [];
        $ip = $request->getAttribute('remote_addr') ?: ($request->serverParams['REMOTE_ADDR'] ?? 'unknown');
        $key = $ip;
        $now = time();
        $bucketStart = (int)floor($now / $window) * $window;

        if (!isset($counts[$key]) || $counts[$key]['window_start'] !== $bucketStart) {
            $counts[$key] = ['window_start' => $bucketStart, 'count' => 0];
        }

        $counts[$key]['count']++;
        return max(0, $limit - $counts[$key]['count']);
    }
}
