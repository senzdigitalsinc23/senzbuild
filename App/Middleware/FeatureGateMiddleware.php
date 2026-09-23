<?php
declare(strict_types=1);


namespace App\Middleware;

use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use PDO;

/**
 * FeatureGateMiddleware — blocks API requests to routes that belong to
 * inactive system features.
 *
 * This runs early in the middleware chain (after CORS/security headers but
 * before auth).  If the requested path starts with any route prefix that is
 * owned by an inactive feature, it immediately returns a 503 JSON response.
 *
 * The inactive-route list is cached in a PHP static variable for the lifetime
 * of the request (no re-query per-request after the first lookup).
 */
class FeatureGateMiddleware implements MiddlewareInterface
{
    /** @var array<string>|null  Cache so we only query once per PHP process/request */
    private static ?array $inactiveRoutes = null;

    public function handle(Request $request, Response $response, callable $next): Response
    {
        $blockedPrefixes = $this->getInactiveRoutePrefixes();

        if (!empty($blockedPrefixes)) {
            $path = $request->getUri(); // e.g. /api/v1/hr/employees

            foreach ($blockedPrefixes as $prefix) {
                // Normalise: strip leading slash for comparison
                $normPrefix = '/' . ltrim($prefix, '/');

                // Match if path equals prefix OR starts with prefix + "/"
                if ($path === $normPrefix || str_starts_with($path, $normPrefix . '/')) {
                    $featureName = $this->getFeatureNameForRoute($prefix);

                    $response->setStatusCode(503);
                    $response->setHeader('Content-Type', 'application/json');
                    $response->setContent(json_encode([
                        'success' => false,
                        'code'    => 'FEATURE_DISABLED',
                        'message' => $featureName
                            ? "The \"{$featureName}\" feature is currently disabled."
                            : 'This feature is currently disabled.',
                    ]));
                    return $response;
                }
            }
        }

        return $next($request, $response);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function getInactiveRoutePrefixes(): array
    {
        if (self::$inactiveRoutes !== null) {
            return self::$inactiveRoutes;
        }

        try {
            $db   = Database::getInstance()->getConnection();
            $stmt = $db->query(
                "SELECT related_routes, name FROM system_features WHERE status = 'inactive'"
            );

            $routes = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $prefixes = json_decode($row['related_routes'] ?? '[]', true);
                if (is_array($prefixes)) {
                    foreach ($prefixes as $prefix) {
                        // Store prefix → feature name for error messages
                        $routes[$prefix] = $row['name'];
                    }
                }
            }

            self::$inactiveRoutes = array_keys($routes);
            // Also keep a name map in a second static for the error message lookup
            self::$routeNameMap = $routes;
        } catch (\Throwable $e) {
            // If the table doesn't exist yet (fresh install), don't block anything
            self::$inactiveRoutes = [];
            self::$routeNameMap   = [];
        }

        return self::$inactiveRoutes;
    }

    /** @var array<string, string>|null  prefix => feature name */
    private static ?array $routeNameMap = null;

    private function getFeatureNameForRoute(string $prefix): ?string
    {
        if (self::$routeNameMap === null) {
            $this->getInactiveRoutePrefixes();
        }
        return self::$routeNameMap[$prefix] ?? null;
    }
}
