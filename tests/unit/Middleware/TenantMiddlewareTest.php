<?php
declare(strict_types=1);

namespace Tests\Unit\Middleware;

use PHPUnit\Framework\TestCase;
use App\Middleware\TenantMiddleware;
use App\Core\Request;
use App\Core\Response;

class TenantMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        TenantMiddleware::reset();
    }

    protected function tearDown(): void
    {
        TenantMiddleware::reset();
    }

    protected function makeRequest(array $headers = []): Request
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/api/v1/products';
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
        foreach ($headers as $name => $value) {
            $_SERVER['HTTP_' . str_replace('-', '_', strtoupper($name))] = $value;
        }
        return new Request();
    }

    public function test_tenant_id_from_header(): void
    {
        $request  = $this->makeRequest(['X-Tenant-ID' => 'acme-corp']);
        $response = new Response();
        $middleware = new TenantMiddleware();

        $middleware->handle($request, $response, function (Request $r, Response $res) {
            return $res;
        });

        $this->assertSame('acme-corp', TenantMiddleware::getCurrentTenantId());
        $this->assertSame('acme-corp', $response->getCustomHeader('X-Tenant-ID'));
    }

    public function test_tenant_id_from_subdomain(): void
    {
        TenantMiddleware::reset();
        $_SERVER['HTTP_HOST'] = 'acme-corp.example.com';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/v1/products';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        // Remove any HTTP_ headers that might interfere
        foreach (array_keys($_SERVER) as $key) {
            if (str_starts_with($key, 'HTTP_') && $key !== 'HTTP_HOST') {
                unset($_SERVER[$key]);
            }
        }

        $request  = new \App\Core\Request();
        $response = new Response();
        $middleware = new TenantMiddleware();

        $middleware->handle($request, $response, fn($r, $res) => $res);

        $this->assertSame('acme-corp', TenantMiddleware::getCurrentTenantId());
        $this->assertSame('acme-corp', $response->getCustomHeader('X-Tenant-ID'));
        unset($_SERVER['HTTP_HOST']);
    }

    public function test_skips_common_subdomains(): void
    {
        TenantMiddleware::reset();
        unset($_SERVER['HTTP_HOST']);
        $_SERVER['HTTP_HOST'] = 'www.example.com';

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/api/v1/products';
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
        foreach (array_keys($_SERVER) as $key) {
            if (str_starts_with($key, 'HTTP_')) unset($_SERVER[$key]);
        }

        $request  = new \App\Core\Request();
        $response = new Response();
        $middleware = new TenantMiddleware();

        $middleware->handle($request, $response, fn($r, $res) => $res);

        $this->assertNull(TenantMiddleware::getCurrentTenantId());
        unset($_SERVER['HTTP_HOST']);
    }

    public function test_no_tenant_without_header_or_subdomain(): void
    {
        TenantMiddleware::reset();
        unset($_SERVER['HTTP_HOST']);
        foreach (array_keys($_SERVER) as $key) {
            if (str_starts_with($key, 'HTTP_')) unset($_SERVER[$key]);
        }

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/api/v1/products';
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';

        $request  = new \App\Core\Request();
        $response = new Response();
        $middleware = new TenantMiddleware();

        $middleware->handle($request, $response, fn($r, $res) => $res);

        $this->assertNull(TenantMiddleware::getCurrentTenantId());
    }

    public function test_reset_clears_state(): void
    {
        $request  = $this->makeRequest(['X-Tenant-ID' => 'tenant-1']);
        $response = new Response();
        (new TenantMiddleware())->handle($request, $response, fn($r, $res) => $res);

        $this->assertSame('tenant-1', TenantMiddleware::getCurrentTenantId());

        TenantMiddleware::reset();
        $this->assertNull(TenantMiddleware::getCurrentTenantId());
    }
}
