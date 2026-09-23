<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Cache;
use App\Core\Logger;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;

/**
 * Per-user rate limiter middleware.
 *
 * Applies token-bucket rate limiting per authenticated user (by user ID)
 * and per-IP for unauthenticated requests. Supports per-endpoint overrides.
 *
 * Configuration:
 *   RATE_LIMIT_MAX       — default max requests per window (default: 60)
 *   RATE_LIMIT_WINDOW    — window size in seconds (default: 60)
 *   RATE_LIMIT_USER_MULT — multiplier for authenticated users (default: 5x)
 *
 * Response headers:
 *   X-RateLimit-Limit     — max requests allowed
 *   X-RateLimit-Remaining — requests left in current window
 *   X-RateLimit-Reset     — Unix timestamp when the window resets
 *   Retry-After           — seconds to wait (only on 429)
 */
class PerUserRateLimiter implements MiddlewareInterface
{
    private Cache $cache;
    private Logger $logger;
    private int $defaultMax;
    private int $window;
    private int $userMultiplier;

    /**
     * @var array<string, int> Per-endpoint overrides: 'path_pattern' => maxRequests
     */
    private array $endpointLimits = [];

    public function __construct(
        ?Cache   $cache          = null,
        ?Logger  $logger         = null,
        int|null $defaultMax     = null,
        int|null $window         = null,
        int|null $userMultiplier = null,
        array    $endpointLimits = []
    ) {
        $this->cache          = $cache      ?? new Cache();
        $this->logger         = $logger     ?? new Logger(dirname(__DIR__, 2) . '/storage/logs/rate_limit.log');
        $this->defaultMax     = $defaultMax ?? (int)($_ENV['RATE_LIMIT_MAX'] ?? 60);
        $this->window         = $window     ?? (int)($_ENV['RATE_LIMIT_WINDOW'] ?? 60);
        $this->userMultiplier = $userMultiplier ?? (int)($_ENV['RATE_LIMIT_USER_MULT'] ?? 5);
        $this->endpointLimits = $endpointLimits;
    }

    public function handle(Request $request, Response $response, callable $next): Response
    {
        $method = $request->getMethod();

        // Skip non-mutating read methods for strict limiting; still track for headers
        $isRead = in_array($method, ['GET', 'HEAD', 'OPTIONS'], true);

        $limit      = $this->resolveLimit($request);
        $key        = $this->resolveKey($request);
        $now        = time();
        $windowStart = (int)floor($now / $this->window) * $this->window;

        $bucketKey = "rl:{$key}:{$windowStart}";
        $count     = (int)($this->cache->get($bucketKey) ?? 0);

        $remaining = max(0, $limit - $count);

        // Always inject headers
        $response->setHeader('X-RateLimit-Limit', (string)$limit);
        $response->setHeader('X-RateLimit-Remaining', (string)$remaining);
        $response->setHeader('X-RateLimit-Reset', (string)($windowStart + $this->window));

        if ($count >= $limit && !$isRead) {
            $this->logger->warning("Rate limit exceeded: key={$key} count={$count} limit={$limit}");
            $retryAfter = $windowStart + $this->window - $now;
            $response->setStatusCode(429);
            $response->setHeader('Retry-After', (string)max(1, $retryAfter));
            $response->setHeader('Content-Type', 'application/json');
            $response->setContent(json_encode([
                'success' => false,
                'error'   => 'RATE_LIMIT_EXCEEDED',
                'message' => "Too many requests. Try again in {$retryAfter}s.",
            ]));
            return $response;
        }

        // Increment counter
        $newCount = $count + 1;
        $this->cache->set($bucketKey, $newCount, $this->window + 10);

        return $next($request, $response);
    }

    /**
     * Resolve the rate limit for the given request.
     */
    private function resolveLimit(Request $request): int
    {
        // Check per-endpoint override
        $path = $request->getPath();
        foreach ($this->endpointLimits as $pattern => $limit) {
            if (str_contains($path, $pattern)) {
                return $limit;
            }
        }

        // Authenticated users get a higher limit
        $userId = $this->getCurrentUserId($request);
        if ($userId !== null) {
            return $this->defaultMax * $this->userMultiplier;
        }

        return $this->defaultMax;
    }

    /**
     * Resolve the cache key for the request (user ID or IP).
     */
    private function resolveKey(Request $request): string
    {
        $userId = $this->getCurrentUserId($request);
        if ($userId !== null) {
            return "user:{$userId}";
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        return "ip:{$ip}";
    }

    /**
     * Get the current authenticated user ID, or null if unauthenticated.
     */
    private function getCurrentUserId(Request $request): ?string
    {
        // Check session
        if (!empty($_SESSION['user_id'])) {
            return (string)$_SESSION['user_id'];
        }

        // Check JWT from Authorization header
        $auth = $request->getHeaderLine('Authorization');
        if ($auth && preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            try {
                $decoded = \Firebase\JWT\JWT::decode($m[1], new \Firebase\JWT\Key($_ENV['JWT_SECRET'] ?? '', 'HS256'));
                return isset($decoded->sub) ? (string)$decoded->sub : null;
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * Add a per-endpoint rate limit override.
     */
    public function addEndpointLimit(string $pathPattern, int $maxRequests): void
    {
        $this->endpointLimits[$pathPattern] = $maxRequests;
    }

    /**
     * Clear the underlying cache (for testing).
     */
    public function clearCache(): void
    {
        $this->cache->clear();
    }
}
