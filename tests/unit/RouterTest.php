<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Core\Router;
use App\Core\Container;
use App\Core\Interfaces\RequestInterface;
use App\Core\Interfaces\ResponseInterface;
use App\Core\Response;

class RouterTest extends TestCase
{
    private Router $router;
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->router = new Router($this->container);
    }

    public function testGetRouteRegistration(): void
    {
        $this->router->get('/test', [stdClass::class, 'method']);
        // We check if the route was added by attempting to dispatch it
        $request = $this->createMock(RequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getPath')->willReturn('/test');

        $response = new Response();

        // We expect an exception because stdClass doesn't have 'method' and Container can't resolve it as a controller
        $this->expectException(\Exception::class);
        $this->router->dispatch($request, $response);
    }

    public function testRouteNotFoundReturns404(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getPath')->willReturn('/not-found');

        $response = new Response();
        $result = $this->router->dispatch($request, $response);

        $this->assertEquals(404, $result->getStatusCode());
        $this->assertStringContainsString('Route not found', $result->getContent());
    }

    public function testDynamicRouteParameters(): void
    {
        // We need a real controller or a mock that the container can resolve
        // For simplicity, let's use a closure if Router supported it, but it expects [class, method]

        $this->router->get('/user/{id}', [stdClass::class, 'show']);

        $request = $this->createMock(RequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getPath')->willReturn('/user/123');

        $response = new Response();

        // We expect exception because stdClass isn't a real controller with a show method
        $this->expectException(\Exception::class);
        $this->router->dispatch($request, $response);
    }
}
