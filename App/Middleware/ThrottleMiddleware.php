<?php
declare(strict_types=1);


namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

class ThrottleMiddleware
{
    private \App\Core\Cache $cache;

    public function __construct(int $maxAttempts = 5, int $decaySeconds = 60)
    {
        $this->maxAttempts = $maxAttempts;
        $this->decaySeconds = $decaySeconds;
        $this->cache = new \App\Core\Cache();
    }

    public function handle(Request $request, Response $response, callable $next): Response
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'throttle_' . hash('sha256', $ip . '|' . ($_SERVER['REQUEST_URI'] ?? '/'));

        $attempts = $this->cache->get($key);

        if ($attempts && $attempts['attempts'] >= $this->maxAttempts) {
            $retryAfter = $attempts['expires_at'] - time();
            if ($retryAfter > 0) {
                $response = new Response();
                $response->setStatusCode(429);
                $response->setHeader('Content-Type', 'application/json');
                $response->setHeader('Retry-After', (string)max(1, $retryAfter));
                $response->setHeader('X-RateLimit-Limit', (string)$this->maxAttempts);
                $response->setHeader('X-RateLimit-Remaining', '0');
                $response->setHeader('X-RateLimit-Reset', (string)$attempts['expires_at']);
                $response->setContent(json_encode([
                    'success' => false,
                    'message' => "Too many attempts. Try again in {$retryAfter} seconds.",
                    'retry_after' => max(1, $retryAfter),
                ]));
                return $response;
            }
        }

        // Record the attempt
        $now = time();
        $data = [
            'attempts' => ($attempts['attempts'] ?? 0) + 1,
            'expires_at' => $now + $this->decaySeconds,
        ];
        $this->cache->set($key, $data, $this->decaySeconds);

        $response = $next($request, $response);

        // Add rate limit headers to the response
        if ($response instanceof Response) {
            $remaining = max(0, $this->maxAttempts - $data['attempts']);
            $response->setHeader('X-RateLimit-Limit', (string)$this->maxAttempts);
            $response->setHeader('X-RateLimit-Remaining', (string)$remaining);
            $response->setHeader('X-RateLimit-Reset', (string)$data['expires_at']);
        }

        return $response;
    }
}
