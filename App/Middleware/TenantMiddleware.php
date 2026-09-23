<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;

/**
 * Tenant scoping middleware for multi-tenancy.
 *
 * Extracts the tenant identifier from the request and attaches it to the
 * request object. Models and repositories can then scope queries to the
 * current tenant automatically.
 *
 * Resolution order:
 *   1. X-Tenant-ID header
 *   2. subdomain (first segment of host)
 *   3. JWT sub claim (if authenticated)
 *   4. API key lookup (from DB)
 *
 * Usage:
 *   // In a model, scope queries to current tenant:
 *   protected static bool $tenantScoped = true;
 *
 *   public static function query(): QueryBuilder
 *   {
 *       $qb = parent::query();
 *       if (static::$tenantScoped) {
 *           $qb->where('tenant_id', TenantMiddleware::getCurrentTenantId());
 *       }
 *       return $qb;
 *   }
 */
class TenantMiddleware implements MiddlewareInterface
{
    private static ?string $currentTenantId = null;
    private static ?string $currentTenantKey = null;

    public function handle(Request $request, Response $response, callable $next): Response
    {
        $tenantId = $this->resolveTenant($request);

        if ($tenantId !== null) {
            self::$currentTenantId   = $tenantId;
            self::$currentTenantKey  = $this->resolveTenantKey($request, $tenantId);
            $request->setAttribute('tenant_id', $tenantId);
            $request->setAttribute('tenant_key', self::$currentTenantKey);
            $response->setHeader('X-Tenant-ID', (string)$tenantId);
        }

        return $next($request, $response);
    }

    /**
     * Get the current tenant ID from the active request context.
     */
    public static function getCurrentTenantId(): ?string
    {
        return self::$currentTenantId;
    }

    /**
     * Get the current tenant key from the active request context.
     */
    public static function getCurrentTenantKey(): ?string
    {
        return self::$currentTenantKey;
    }

    /**
     * Reset tenant state (call between requests in CLI / tests).
     */
    public static function reset(): void
    {
        self::$currentTenantId  = null;
        self::$currentTenantKey = null;
    }

    /**
     * Resolve tenant ID from the request.
     */
    private function resolveTenant(Request $request): ?string
    {
        // 1. Header
        $header = $request->getHeaderLine('X-Tenant-ID');
        if (!empty($header)) {
            return trim($header);
        }

        // 2. Subdomain
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (str_contains($host, '.')) {
            $subdomain = explode('.', $host, 2)[0];
            // Skip common non-tenant subdomains
            if (!in_array($subdomain, ['www', 'api', 'mail', 'cdn', 'static'], true)) {
                return $subdomain;
            }
        }

        // 3. JWT sub claim
        $auth = $request->getHeaderLine('Authorization');
        if ($auth && preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            try {
                $decoded = \Firebase\JWT\JWT::decode(
                    $m[1],
                    new \Firebase\JWT\Key($_ENV['JWT_SECRET'] ?? '', 'HS256')
                );
                if (isset($decoded->tenant_id)) {
                    return (string)$decoded->tenant_id;
                }
                if (isset($decoded->sub) && !str_contains((string)$decoded->sub, '@')) {
                    // Could be a tenant slug
                    return (string)$decoded->sub;
                }
            } catch (\Throwable) {
                // Ignore invalid tokens
            }
        }

        return null;
    }

    /**
     * Resolve the internal tenant key (database ID) from a tenant identifier.
     */
    private function resolveTenantKey(Request $request, string $tenantId): ?string
    {
        // Try API key lookup if configured
        $apiKey = $request->getHeaderLine('X-API-Key');
        if (!empty($apiKey) && class_exists(\App\Repositories\ApiKeyRepo::class)) {
            try {
                $repo = new \App\Repositories\ApiKeyRepo();
                $keyData = $repo->findByApiKey($apiKey);
                if ($keyData && isset($keyData['tenant_id'])) {
                    return (string)$keyData['tenant_id'];
                }
            } catch (\Throwable) {
                // Fallback to raw tenant ID
            }
        }

        return $tenantId;
    }
}
