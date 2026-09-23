<?php
declare(strict_types=1);


namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

/**
 * CSRF protection middleware.
 *
 * Skipped automatically for:
 *  - Stateless JWT API requests (Authorization: Bearer …)
 *  - Requests authenticated via X-API-Key header
 *  - OPTIONS preflight requests
 */
class CsrfMiddleware
{
    public function handle($request = null, $response = null, $next = null)
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Only protect state-changing methods
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            if ($next) return $next($request, $response);
            return $response;
        }

        // Skip for OPTIONS preflight
        if ($method === 'OPTIONS') {
            if ($next) return $next($request, $response);
            return $response;
        }

        $headers = function_exists('getallheaders') ? getallheaders() : [];
        // Normalise header keys to lowercase for reliable lookup
        $headers = array_change_key_case($headers, CASE_LOWER);

        // Skip CSRF for stateless API requests (Bearer JWT or API key)
        $hasBearer = !empty($headers['authorization']) &&
                     stripos($headers['authorization'], 'Bearer ') === 0;
        $hasApiKey = !empty($headers['x-api-key']);

        if ($hasBearer || $hasApiKey) {
            if ($next) return $next($request, $response);
            return $response;
        }

        // Web session CSRF check
        $token = $headers['x-csrf-token'] ?? ($request ? $request->input('_csrf', '') : '');

        if (!$token || $token !== ($_SESSION['_csrf_token'] ?? '')) {
            $resp = $response ?? new Response();
            $resp->setStatusCode(419);
            $resp->setHeader('Content-Type', 'application/json');
            $resp->setContent(json_encode([
                'success' => false,
                'message' => 'Invalid request. CSRF token mismatch.',
            ]));
            return $resp;
        }

        if ($next) return $next($request, $response);
        return $response;
    }
}
