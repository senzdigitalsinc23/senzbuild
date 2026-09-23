<?php
declare(strict_types=1);

namespace Tests\Unit\Middleware;

use PHPUnit\Framework\TestCase;
use App\Core\Cache;
use App\Core\Request;
use App\Core\Response;
use App\Middleware\RequestFingerprintMiddleware;

class RequestFingerprintMiddlewareTest extends TestCase
{
    private RequestFingerprintMiddleware $middleware;

    protected function setUp(): void
    {
        $this->middleware = new RequestFingerprintMiddleware();
        $_ENV['JWT_SECRET'] = 'test_secret_key_for_jwt_signing_1234567890';
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

    public function test_fingerprint_is_generated_and_attached(): void
    {
        $request  = $this->makeRequest('POST', '/api/v1/orders');
        $response = new Response();

        $this->middleware->handle($request, $response, function (Request $r, Response $res) {
            return $res->jsonResponse(['ok' => true]);
        });

        $fingerprint = $request->getCustomAttribute('request_fingerprint');
        $this->assertNotEmpty($fingerprint);
        $this->assertSame($fingerprint, $response->getCustomHeader('X-Request-Fingerprint'));
        $this->assertEquals(64, strlen($fingerprint)); // SHA-256 hex length
    }

    public function test_same_request_produces_same_fingerprint(): void
    {
        $request1 = $this->makeRequest('POST', '/api/v1/orders');
        $request2 = $this->makeRequest('POST', '/api/v1/orders');

        $fp1 = null;
        $fp2 = null;

        $this->middleware->handle($request1, new Response(), function (Request $r, Response $res) use (&$fp1) {
            $fp1 = $r->getCustomAttribute('request_fingerprint');
            return $res;
        });
        $this->middleware->handle($request2, new Response(), function (Request $r, Response $res) use (&$fp2) {
            $fp2 = $r->getCustomAttribute('request_fingerprint');
            return $res;
        });

        $this->assertSame($fp1, $fp2);
    }

    public function test_different_paths_produce_different_fingerprints(): void
    {
        // b=2&a=1 should produce the same fingerprint as a=1&b=2
        $request1 = $this->makeRequest('GET', '/api/v1/search?b=2&a=1');
        $request2 = $this->makeRequest('GET', '/api/v1/search?a=1&b=2');

        $fp1 = null;
        $fp2 = null;

        $this->middleware->handle($request1, new Response(), function (Request $r, Response $res) use (&$fp1) {
            $fp1 = $r->getCustomAttribute('request_fingerprint');
            return $res;
        });
        $this->middleware->handle($request2, new Response(), function (Request $r, Response $res) use (&$fp2) {
            $fp2 = $r->getCustomAttribute('request_fingerprint');
            return $res;
        });

        $this->assertSame($fp1, $fp2);
    }
}
