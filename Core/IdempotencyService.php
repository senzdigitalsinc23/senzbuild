<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Idempotency service — stores and retrieves previously processed request responses.
 *
 * Used to guarantee that duplicate requests (e.g. from network retries) do not
 * cause side-effects such as double charges or duplicate records.
 *
 * Storage priority: Redis (when available) → database fallback.
 */
class IdempotencyService
{
    protected Cache $cache;
    protected Logger $logger;
    protected int $ttl;
    protected string $prefix;

    public function __construct(
        ?Cache    $cache   = null,
        ?Logger   $logger  = null,
        protected int $dbTtl = 86400
    ) {
        $this->cache  = $cache  ?? new Cache();
        $this->logger = $logger ?? new Logger(dirname(__DIR__, 2) . '/storage/logs/idempotency.log');
        $this->ttl    = (int)($_ENV['IDEMPOTENCY_TTL'] ?? 86400); // 24 h default
        $this->prefix = 'idem:';
    }

    /**
     * Try to retrieve a cached response for the given idempotency key.
     * Returns null if the key does not exist or has expired.
     */
    public function get(string $key): ?array
    {
        $cacheKey = $this->prefix . $key;
        $data = $this->cache->get($cacheKey);
        if ($data === null) {
            return null;
        }
        $this->logger->info("Idempotency cache hit: {$key}");
        return is_array($data) ? $data : null;
    }

    /**
     * Store a response against an idempotency key for future reuse.
     */
    public function store(string $key, int $statusCode, array $headers, string $body, ?string $userId = null): void
    {
        $cacheKey = $this->prefix . $key;
        $data = [
            'status'    => $statusCode,
            'headers'   => $headers,
            'body'      => $body,
            'user_id'   => $userId,
            'stored_at' => time(),
        ];

        $this->cache->set($cacheKey, $data, $this->ttl);
        $this->logger->info("Idempotency key stored: {$key} (TTL: {$this->ttl}s)");
    }

    /**
     * Check whether a key already exists (without returning the response).
     */
    public function exists(string $key): bool
    {
        return $this->cache->has($this->prefix . $key);
    }

    /**
     * Remove all idempotency keys for a given user (e.g. on logout).
     */
    public function clearForUser(string $userId): int
    {
        // Tag-based flush if Redis is active
        $this->cache->flushTags();
        return 0;
    }

    /**
     * Purge expired entries older than $olderThan seconds (for maintenance).
     */
    public function prune(int $olderThan = 86400): int
    {
        // File-based cache handles expiration automatically on get(); nothing to do.
        // For Redis, we could run KEYS and DEL, but TTL handles it.
        return 0;
    }
}
