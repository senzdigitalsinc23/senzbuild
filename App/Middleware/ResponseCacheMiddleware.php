<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Cache;
use App\Core\Logger;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;

/**
 * Response cache middleware.
 *
 * Caches GET responses in the application cache layer so repeated identical
 * requests return the stored response without hitting controllers or the DB.
 *
 * Behaviour:
 *   - Only applies to GET requests.
 *   - Skips caching for authenticated users (private data).
 *   - Skips caching when the request carries Cache-Control: no-cache.
 *   - Skips caching on error responses (≥ 400).
 *   - Adds X-Cache: HIT / MISS header to every response.
 *   - TTL is read from IDEMPOTENCY_CACHE_TTL env (default 60 s).
 *
 * Cache key = md5("{$method}|{$path}|{$queryString}")
 */
class ResponseCacheMiddleware implements MiddlewareInterface
{
    private Cache $cache;
    private Logger $logger;
    private int $ttl;
    private bool $enabled;

    public function __construct(
        ?Cache   $cache  = null,
        ?Logger  $logger = null,
        int|null $ttl    = null,
        bool|null $enabled = null
    ) {
        $this->cache  = $cache  ?? new Cache();
        $this->logger = $logger ?? new Logger(dirname(__DIR__, 2) . '/storage/logs/response_cache.log');
        $this->ttl    = $ttl    ?? (int)($_ENV['IDEMPOTENCY_CACHE_TTL'] ?? 60);
        $this->enabled = $enabled ?? ($_ENV['RESPONSE_CACHE_ENABLED'] ?? 'true') === 'true';
    }

    public function handle(Request $request, Response $response, callable $next): Response
    {
        if (!$this->enabled) {
            return $next($request, $response);
        }

        // Only cache GET requests
        if ($request->getMethod() !== 'GET') {
            return $next($request, $response);
        }

        // Skip if client explicitly asked for no-cache
        $cacheControl = $request->getHeaderLine('Cache-Control');
        if (str_contains($cacheControl, 'no-cache') || str_contains($cacheControl, 'no-store')) {
            return $next($request, $response);
        }

        // Skip if user is authenticated (private data)
        if ($this->isAuthenticated($request)) {
            return $next($request, $response);
        }

        $cacheKey = $this->buildCacheKey($request);
        $cached   = $this->cache->get($cacheKey);

        if ($cached !== null && is_array($cached)) {
            // Cache HIT
            $this->logger->info("Cache HIT for {$request->getPath()}");
            $response->setStatusCode((int)$cached['status']);
            foreach (($cached['headers'] ?? []) as $name => $value) {
                $response->setHeader($name, $value);
            }
            $response->setHeader('X-Cache', 'HIT');
            $response->setHeader('Age', (string)max(0, time() - ($cached['cached_at'] ?? time())));
            $response->setContent($cached['body']);
            return $response;
        }

        // Cache MISS — run the request
        $response = $next($request, $response);

        // Only cache successful responses
        $status  = $response->getStatusCode();
        $content = $response->getContent();

        if ($status >= 200 && $status < 300 && strlen($content) > 0) {
            $this->cache->set($cacheKey, [
                'status'  => $status,
                'headers' => $this->collectSafeHeaders($response),
                'body'    => $content,
                'cached_at' => time(),
            ], $this->ttl);
            $this->logger->info("Cache MISS for {$request->getPath()} — stored (TTL: {$this->ttl}s)");
        }

        $response->setHeader('X-Cache', 'MISS');
        return $response;
    }

    private function buildCacheKey(Request $request): string
    {
        $path      = $request->getPath();
        $queryString = $request->getUri()->getQuery();
        $raw = "{$request->getMethod()}|{$path}|{$queryString}";
        return 'resp_cache:' . md5($raw);
    }

    private function isAuthenticated(Request $request): bool
    {
        $auth = $request->getHeaderLine('Authorization');
        if ($auth && preg_match('/^Bearer\s+/i', $auth)) {
            return true;
        }
        return !empty($_SESSION['user_id'] ?? null);
    }

    /**
     * Collect non-sensitive headers for caching.
     */
    private function collectSafeHeaders(Response $response): array
    {
        $sensitive = ['Set-Cookie', 'Authorization', 'X-CSRF-Token'];
        $headers   = [];

        // Built-in headers (PSR-7 getHeaders returns array of arrays)
        foreach ($response->getHeaders() as $name => $values) {
            if (in_array($name, $sensitive, true)) continue;
            $headers[$name] = implode(', ', (array)$values);
        }
        return $headers;
    }
}
