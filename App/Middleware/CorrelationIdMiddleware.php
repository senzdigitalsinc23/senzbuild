<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\CorrelationId;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;

/**
 * Middleware that injects a Correlation ID into every request and response.
 *
 * If the request already carries an X-Correlation-ID header, it is preserved.
 * Otherwise a new UUID v4 is generated. The ID is:
 *   - Added to the response header X-Correlation-ID
 *   - Attached to the request via setAttribute('correlation_id', ...)
 *   - Available via CorrelationId::get() throughout the request lifecycle
 */
class CorrelationIdMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Response $response, callable $next): Response
    {
        $id = CorrelationId::init($request);

        // Attach to request for downstream middleware / controllers
        if (method_exists($request, 'setAttribute')) {
            $request->setAttribute('correlation_id', $id);
        }

        // Add to response headers
        $response->setHeader('X-Correlation-ID', $id);

        return $next($request, $response);
    }
}
