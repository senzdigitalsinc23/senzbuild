<?php
declare(strict_types=1);


namespace App\Middleware;

use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;

/**
 * Request tracking middleware
 * 
 * Tracks request IDs, response times, and adds standard headers
 * for better observability and debugging.
 */
class RequestTrackingMiddleware implements MiddlewareInterface
{
    /**
     * Handle the request
     *
     * @param Request $request
     * @param Response $response
     * @param callable $next
     * @return Response
     */
    public function handle(Request $request, Response $response, callable $next): Response
    {
        // Generate unique request ID
        $requestId = $this->generateRequestId();
        
        // Store request ID in request (if method exists)
        if (method_exists($request, 'setAttribute')) {
            $request->setAttribute('request_id', $requestId);
        }
        
        // Track start time
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        // Process request
        $response = $next($request, $response);

        // Calculate metrics
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $memoryUsed = $this->formatBytes(memory_get_usage() - $startMemory);
        $peakMemory = $this->formatBytes(memory_get_peak_usage());

        // Add tracking headers
        $response->setHeader('X-Request-ID', $requestId);
        $response->setHeader('X-Response-Time', $duration . 'ms');
        $response->setHeader('X-Memory-Used', $memoryUsed);
        $response->setHeader('X-Memory-Peak', $peakMemory);
        $response->setHeader('X-Powered-By', 'BASTURMS/1.0');
        
        // Add CORS headers if not already set
        if (!$response->hasHeader('Access-Control-Allow-Origin')) {
            $this->addCorsHeaders($request, $response);
        }

        // Log request
        $this->logRequest($requestId, $request, $response, $duration);

        return $response;
    }

    /**
     * Generate unique request ID
     *
     * @return string
     */
    private function generateRequestId(): string
    {
        return sprintf(
            'req_%s_%s',
            date('YmdHis'),
            bin2hex(random_bytes(8))
        );
    }

    /**
     * Log request details
     *
     * @param string $requestId
     * @param Request $request
     * @param Response $response
     * @param float $duration
     * @return void
     */
    private function logRequest(
        string $requestId,
        Request $request,
        Response $response,
        float $duration
    ): void {
        $method = $request->getMethod();
        $path = $request->getPath();
        $statusCode = $response->getStatusCode();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // Determine log level based on status code
        $logLevel = $this->getLogLevel($statusCode);
        
        $logMessage = sprintf(
            '[%s] %s %s %s - %d - %.2fms - %s',
            $requestId,
            $method,
            $path,
            $ip,
            $statusCode,
            $duration,
            $this->getStatusText($statusCode)
        );

        // Log based on level
        if ($logLevel === 'error') {
            error_log("ERROR: {$logMessage}");
        } elseif ($logLevel === 'warning') {
            error_log("WARNING: {$logMessage}");
        } else {
            error_log("INFO: {$logMessage}");
        }

        // Log slow requests (> 1 second)
        if ($duration > 1000) {
            error_log("SLOW REQUEST: {$logMessage}");
        }
    }

    /**
     * Get log level based on status code
     *
     * @param int $statusCode
     * @return string
     */
    private function getLogLevel(int $statusCode): string
    {
        if ($statusCode >= 500) {
            return 'error';
        } elseif ($statusCode >= 400) {
            return 'warning';
        }
        return 'info';
    }

    /**
     * Get status text for status code
     *
     * @param int $statusCode
     * @return string
     */
    private function getStatusText(int $statusCode): string
    {
        $statusTexts = [
            200 => 'OK',
            201 => 'Created',
            204 => 'No Content',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            422 => 'Validation Error',
            500 => 'Server Error',
            503 => 'Service Unavailable',
        ];

        return $statusTexts[$statusCode] ?? 'Unknown';
    }

    /**
     * Format bytes to human-readable format
     *
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . $units[$pow];
    }

    /**
     * Add CORS headers
     *
     * @param Response $response
     * @return void
     */
    private function addCorsHeaders(Request $request, Response $response): void
    {
        $origin = $request->getHeader('Origin', '');
        $envOrigins = trim($_ENV['CORS_ALLOWED_ORIGINS'] ?? '', " \t\"'");
        $allowedOrigins = $envOrigins !== '' ? array_map('trim', explode(',', $envOrigins)) : ['*'];

        // Echo back a single allowed origin (browser requires exactly one value)
        if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
            $response->setHeader('Access-Control-Allow-Origin', $origin);
        } elseif (in_array('*', $allowedOrigins, true)) {
            $response->setHeader('Access-Control-Allow-Origin', '*');
        } elseif (!empty($allowedOrigins)) {
            $response->setHeader('Access-Control-Allow-Origin', $allowedOrigins[0]);
        }

        $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        $response->setHeader('Access-Control-Max-Age', '86400');
    }
}
