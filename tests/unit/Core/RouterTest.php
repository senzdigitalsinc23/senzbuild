<?php

namespace Tests\Unit\Core;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    private Container $container;
    private Router $router;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->container->singleton(Request::class, fn() => new Request());
        $this->container->singleton(Response::class, fn() => new Response());
        $this->router = new Router($this->container);
    }

    public function test_registers_and_matches_get_route(): void
    {
        $this->router->get('/test/hello', function (Request $req, Response $res) {
            $res->setStatusCode(200);
            $res->setHeader('Content-Type', 'application/json');
            $res->setContent(json_encode(['message' => 'Hello']));
            return $res;
        });

        $request = $this->createRequest('GET', '/test/hello');
        $response = $this->router->dispatch($request, new Response());

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame('Hello', $body['message']);
    }

    public function test_returns_404_for_unregistered_route(): void
    {
        $request = $this->createRequest('GET', '/nonexistent');
        $response = $this->router->dispatch($request, new Response());

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_extracts_route_parameters(): void
    {
        $this->router->get('/users/{id}', function (Request $req, Response $res, array $params) {
            $res->setStatusCode(200);
            $res->setContent(json_encode(['user_id' => $params['id']]));
            return $res;
        });

        $request = $this->createRequest('GET', '/users/42');
        $response = $this->router->dispatch($request, new Response());

        $body = json_decode($response->getContent(), true);
        $this->assertSame('42', $body['user_id']);
    }

    public function test_middleware_runs_before_controller(): void
    {
        $tracker = new class {
            public int $calls = 0;
        };

        $this->router->get('/guarded', function (Request $req, Response $res) {
            $res->setStatusCode(200);
            $res->setContent(json_encode(['ok' => true]));
            return $res;
        }, [
            function (Request $req, callable $next) use ($tracker) {
                $tracker->calls++;
                return $next($req);
            },
        ]);

        $request = $this->createRequest('GET', '/guarded');
        $this->router->dispatch($request, new Response());

        $this->assertSame(1, $tracker->calls);
    }

    private function createRequest(string $method, string $uri): Request
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        return new Request();
    }
}
