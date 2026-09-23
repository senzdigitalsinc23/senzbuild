<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;

/**
 * Request fingerprinting middleware.
 *
 * Generates a deterministic fingerprint for each request based on:
 *   - HTTP method
 *   - Path
 *   - Query string (sorted)
 *   - Request body hash
 *   - Authenticated user ID (if any)
 *
 * The fingerprint is stored on the request as `request_fingerprint` and
 * can be used for duplicate detection, audit logging, and abuse prevention.
 *
 * Usage:
 *   // In middleware or controller:
 *   $fingerprint = $request->getAttribute('request_fingerprint');
 */
class RequestFingerprintMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Response $response, callable $next): Response
    {
        $fingerprint = $this->generateFingerprint($request);
        $request->setAttribute('request_fingerprint', $fingerprint);
        $response->setHeader('X-Request-Fingerprint', $fingerprint);

        return $next($request, $response);
    }

    /**
     * Generate a SHA-256 fingerprint for the request.
     */
    private function generateFingerprint(Request $request): string
    {
        $method  = $request->getMethod();
        $path    = $request->getPath();
        $query   = $this->sortQueryString($request->getUri()->getQuery());
        $body    = (string)$request->getBody();
        $user    = $this->resolveUserId($request);

        $raw = "{$method}|{$path}|{$query}|{$body}|{$user}";
        return hash('sha256', $raw);
    }

    /**
     * Sort query string parameters for deterministic fingerprinting.
     */
    private function sortQueryString(string $query): string
    {
        if (empty($query)) {
            return '';
        }

        $params = [];
        foreach (explode('&', $query) as $pair) {
            if (str_contains($pair, '=')) {
                [$k, $v] = explode('=', $pair, 2);
                $params[urldecode($k)] = urldecode($v);
            } else {
                $params[urldecode($pair)] = '';
            }
        }

        ksort($params);
        return http_build_query($params);
    }

    /**
     * Resolve authenticated user ID from the request.
     */
    private function resolveUserId(Request $request): string
    {
        if (!empty($_SESSION['user_id'])) {
            return (string)$_SESSION['user_id'];
        }

        $auth = $request->getHeaderLine('Authorization');
        if ($auth && preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            try {
                $decoded = \Firebase\JWT\JWT::decode(
                    $m[1],
                    new \Firebase\JWT\Key($_ENV['JWT_SECRET'] ?? '', 'HS256')
                );
                return isset($decoded->sub) ? (string)$decoded->sub : '';
            } catch (\Throwable) {
                return '';
            }
        }

        return '';
    }
}
