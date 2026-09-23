<?php
declare(strict_types=1);

namespace Tests\Unit\Middleware;

use PHPUnit\Framework\TestCase;
use App\Core\Cache;
use App\Core\Request;
use App\Core\Response;
use App\Middleware\ResponseCacheMiddleware;

class ResponseCacheMiddlewareTest extends TestCase
{
    private Cache $cache;
    private ResponseCacheMiddleware $middleware;

    protected function setUp(): void
    {
        $this->cache      = new Cache();
        $this->middleware = new ResponseCacheMiddleware($this->cache, ttl: 60, enabled: true);
        $_ENV['RESPONSE_CACHE_ENABLED'] = 'true';
        $this->cache->clear();
    }

    protected function makeRequest(string $method, string $path, array $headers = []): Request
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI']    = $path;
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';

        $request = new Request();
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        return $request;
    }

    public function test_get_hit_returns_cached_response(): void
    {
        $path = '/api/v1/public/products';
        $request  = $this->makeRequest('GET', $path);
        $response = new Response();

        $callCount = 0;
        $handler = function (Request $req, Response $res) use (&$callCount) {
            $callCount++;
            $res->jsonResponse(['items' => [1, 2, 3]]);
            return $res;
        };

        $result = $this->middleware->handle($request, $response, $handler);
        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame('MISS', $result->getCustomHeader('X-Cache'));
        $this->assertSame(1, $callCount);

        // Second call — should be a HIT and not invoke handler
        $result2 = $this->middleware->handle($request, $response, $handler);
        $this->assertSame(200, $result2->getStatusCode());
        $this->assertSame('HIT', $result2->getCustomHeader('X-Cache'));
        $this->assertSame(1, $callCount); // still 1 — handler not called again
    }

    public function test_post_not_cached(): void
    {
        $request  = $this->makeRequest('POST', '/api/v1/checkout');
        $response = new Response();
        $callCount = 0;

        $handler = function (Request $req, Response $res) use (&$callCount) {
            $callCount++;
            $res->jsonResponse(['id' => 42], 201);
            return $res;
        };

        $result = $this->middleware->handle($request, $response, $handler);
        $this->assertSame(201, $result->getStatusCode());
        $this->assertSame(1, $callCount);
    }

    public function test_authenticated_request_not_cached(): void
    {
        $_SESSION['user_id'] = 'u-123';

        $request  = $this->makeRequest('GET', '/api/v1/private/orders');
        $response = new Response();
        $callCount = 0;

        $handler = function (Request $req, Response $res) use (&$callCount) {
            $callCount++;
            $res->jsonResponse(['orders' => []]);
            return $res;
        };

        $this->middleware->handle($request, $response, $handler);
        $this->assertSame(1, $callCount);

        // Second call — should NOT be cached because user is authenticated
        $this->middleware->handle($request, $response, $handler);
        $this->assertSame(2, $callCount);

        unset($_SESSION['user_id']);
    }

    public function test_no_cache_header_skips_caching(): void
    {
        $request  = $this->makeRequest('GET', '/api/v1/skip', ['Cache-Control' => 'no-cache']);
        $response = new Response();
        $callCount = 0;

        $handler = function (Request $req, Response $res) use (&$callCount) {
            $callCount++;
            $res->jsonResponse(['data' => 'x']);
            return $res;
        };

        $this->middleware->handle($request, $response, $handler);
        $this->middleware->handle($request, $response, $handler);
        $this->assertSame(2, $callCount); // handler called twice — no caching
    }

    public function test_disabled_cache_does_nothing(): void
    {
        $disabled = new ResponseCacheMiddleware($this->cache, ttl: 60, enabled: false);
        $request  = $this->makeRequest('GET', '/api/v1/test');
        $response = new Response();
        $callCount = 0;

        $handler = function (Request $req, Response $res) use (&$callCount) {
            $callCount++;
            $res->jsonResponse(['ok' => true]);
            return $res;
        };

        $disabled->handle($request, $response, $handler);
        $disabled->handle($request, $response, $handler);
        $this->assertSame(2, $callCount);
    }
}
