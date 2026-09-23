<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\IdempotencyService;
use App\Core\Logger;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;

/**
 * Idempotency middleware.
 *
 * For POST / PUT / PATCH / DELETE requests carrying an Idempotency-Key header:
 *   - If the key already has a cached response, return it immediately (200/201/etc).
 *   - Otherwise, process the request normally and cache the response keyed by the idempotency key.
 *
 * The key must be a non-empty string of 1–128 characters.
 *
 * Response headers added when a cached response is served:
 *   X-Idempotency-Cached: true
 */
class IdempotencyMiddleware implements MiddlewareInterface
{
    private IdempotencyService $service;
    private Logger $logger;
    private int $maxKeyLength;

    public function __construct(
        ?IdempotencyService $service  = null,
        ?Logger             $logger   = null,
        int                 $maxKeyLength = 128
    ) {
        $this->service     = $service ?? new IdempotencyService();
        $this->logger      = $logger  ?? new Logger(dirname(__DIR__, 2) . '/storage/logs/idempotency.log');
        $this->maxKeyLength = $maxKeyLength;
    }

    public function handle(Request $request, Response $response, callable $next): Response
    {
        $method = $request->getMethod();

        // Only applicable to mutating methods
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request, $response);
        }

        $key = $request->header('Idempotency-Key');

        if (empty($key)) {
            // No key provided — process normally
            return $next($request, $response);
        }

        $key = trim($key);

        if (strlen($key) > $this->maxKeyLength || strlen($key) === 0) {
            $response->setStatusCode(400);
            $response->setHeader('Content-Type', 'application/json');
            $response->setContent(json_encode([
                'success' => false,
                'error'   => 'INVALID_IDEMPOTENCY_KEY',
                'message' => "Idempotency-Key must be between 1 and {$this->maxKeyLength} characters.",
            ]));
            return $response;
        }

        // Check for existing response
        $cached = $this->service->get($key);
        if ($cached !== null) {
            $this->logger->info("Idempotency hit for key: {$key} (method: {$method})");
            $response->setStatusCode($cached['status']);
            foreach ($cached['headers'] as $hKey => $hVal) {
                $response->setHeader($hKey, $hVal);
            }
            $response->setHeader('X-Idempotency-Cached', 'true');
            $response->setContent($cached['body']);
            return $response;
        }

        // Process the request
        $response = $next($request, $response);

        // Cache the response only for successful outcomes
        $body = $response->getContent();
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300 && !empty($body)) {
            $this->service->store(
                $key,
                $response->getStatusCode(),
                $this->collectResponseHeaders($response),
                $body,
                $request->getAttribute('correlation_id')
            );
        }

        return $response;
    }

    /**
     * Collect non-sensitive headers from the response for caching.
     */
    private function collectResponseHeaders(Response $response): array
    {
        $sensitive = ['Set-Cookie', 'Authorization', 'X-CSRF-Token'];
        $headers   = [];
        foreach ($response->getHeaders() as $name => $values) {
            if (in_array($name, $sensitive, true)) {
                continue;
            }
            $headers[$name] = implode(', ', (array)$values);
        }
        // Also grab custom headers set via setHeader()
        foreach ($response->headers as $name => $value) {
            if (!in_array($name, $sensitive, true)) {
                $headers[$name] = $value;
            }
        }
        return $headers;
    }
}
