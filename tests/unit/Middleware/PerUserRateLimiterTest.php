<?php
declare(strict_types=1);

namespace Tests\Unit\Middleware;

use PHPUnit\Framework\TestCase;
use App\Core\Cache;
use App\Core\Request;
use App\Core\Response;
use App\Middleware\PerUserRateLimiter;

class PerUserRateLimiterTest extends TestCase
{
    private Cache $cache;
    private PerUserRateLimiter $limiter;

    protected function setUp(): void
    {
        $this->cache   = new Cache();
        $this->limiter = new PerUserRateLimiter($this->cache, defaultMax: 5, window: 60);
        $this->cache->clear();
    }

    protected function makeRequest(string $method, string $path, array $extra = []): Request
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI']    = $path;
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
        foreach ($extra as $k => $v) {
            $_SERVER[$k] = $v;
        }
        return new Request();
    }

    public function test_allowed_requests_incr_count(): void
    {
        // Create a completely fresh cache to avoid cross-test contamination
        $isolatedCache = new Cache();
        $isolatedCache->clear();
        $freshLimiter = new PerUserRateLimiter($isolatedCache, defaultMax: 5, window: 60);

        // Rebuild $_SERVER to match makeRequest exactly
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/api/v1/orders';
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
        unset($_SERVER['HTTP_AUTHORIZATION']);

        $request  = new Request();
        $response = new Response();
        $callCount = 0;

        $handler = function (Request $r, Response $res) use (&$callCount) {
            $callCount++;
            $res->jsonResponse(['ok' => true]);
            return $res;
        };

        // 5 requests should succeed
        for ($i = 0; $i < 5; $i++) {
            $result = $freshLimiter->handle($request, clone $response, $handler);
            $this->assertSame(200, $result->getStatusCode());
        }
        $this->assertSame(5, $callCount);

        // 6th should be rate-limited
        $result = $freshLimiter->handle($request, clone $response, $handler);
        $this->assertSame(429, $result->getStatusCode());
        $this->assertSame(5, $callCount); // handler not called
    }

    public function test_rate_limit_headers_present(): void
    {
        $request  = $this->makeRequest('GET', '/api/v1/products');
        $response = new Response();

        $this->limiter->handle($request, $response, function (Request $r, Response $res) {
            return $res->jsonResponse(['items' => []]);
        });

        $this->assertTrue($response->hasHeader('X-RateLimit-Limit'));
        $this->assertTrue($response->hasHeader('X-RateLimit-Remaining'));
        $this->assertTrue($response->hasHeader('X-RateLimit-Reset'));
    }

    public function test_authenticated_user_gets_higher_limit(): void
    {
        // Use a dedicated limiter with a clean cache so previous tests don't interfere
        $authLimiter = new PerUserRateLimiter(null, null, 5, 60, 5);
        $authLimiter->clearCache();

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer dummy_token';
        $_ENV['JWT_SECRET'] = 'test_secret_key_for_jwt_signing_1234567890';

        $token = \Firebase\JWT\JWT::encode([
            'sub' => 'user-123',
            'iat' => time(),
            'exp' => time() + 3600,
        ], $_ENV['JWT_SECRET'], 'HS256');

        $request  = $this->makeRequest('POST', '/api/v1/orders', ['HTTP_AUTHORIZATION' => "Bearer {$token}"]);
        $response = new Response();
        $callCount = 0;

        $handler = function (Request $r, Response $res) use (&$callCount) {
            $callCount++;
            $res->jsonResponse(['ok' => true]);
            return $res;
        };

        // Authenticated user gets 5x limit = 25 requests
        for ($i = 0; $i < 25; $i++) {
            $result = $authLimiter->handle($request, clone $response, $handler);
            $this->assertSame(200, $result->getStatusCode());
        }
        $this->assertSame(25, $callCount);
    }

    public function test_get_requests_not_blocked_on_limit(): void
    {
        $request  = $this->makeRequest('GET', '/api/v1/products');
        $response = new Response();
        $callCount = 0;

        $handler = function (Request $r, Response $res) use (&$callCount) {
            $callCount++;
            $res->jsonResponse(['items' => []]);
            return $res;
        };

        // Fill the bucket
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->handle($request, clone $response, $handler);
        }

        // GET should still go through even if bucket is full
        $result = $this->limiter->handle($request, clone $response, $handler);
        $this->assertSame(200, $result->getStatusCode());
    }

    public function test_endpoint_override(): void
    {
        // Use a fresh limiter so previous tests don't affect the count
        $epLimiter = new PerUserRateLimiter(new Cache(), null, 5, 60, 5);
        $epLimiter->clearCache();
        $epLimiter->addEndpointLimit('/api/v1/login', 3);

        $request  = $this->makeRequest('POST', '/api/v1/login');
        $response = new Response();
        $callCount = 0;

        $handler = function (Request $r, Response $res) use (&$callCount) {
            $callCount++;
            $res->jsonResponse(['ok' => true]);
            return $res;
        };

        for ($i = 0; $i < 3; $i++) {
            $result = $epLimiter->handle($request, clone $response, $handler);
            $this->assertSame(200, $result->getStatusCode());
        }

        $result = $epLimiter->handle($request, clone $response, $handler);
        $this->assertSame(429, $result->getStatusCode());
    }
}
