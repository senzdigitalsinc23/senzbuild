<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Container;

class TerminableMiddlewareTest extends TestCase
{
    public function test_terminate_called_after_handle(): void
    {
        $state = new class {
            public bool $terminated = false;
        };

        $container = new Container();
        $container->singleton(TerminableMW::class, fn() => new TerminableMW($state));

        $router = new Router($container);
        $router->middleware([TerminableMW::class]);
        $router->get('/test', [TerminableController::class, 'index'], []);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $request = new Request();
        $response = new Response();

        $router->dispatch($request, $response);
        $this->assertTrue($state->terminated, 'terminate() should be called after handle()');
    }

    public function test_terminate_on_route_middleware(): void
    {
        $state = new class {
            public bool $terminated = false;
        };

        $container = new Container();
        $container->singleton(RouteTerminableMW::class, fn() => new RouteTerminableMW($state));

        $router = new Router($container);
        // Only route middleware, no global middleware
        $router->getApi('v1', '/route-test', [TerminableController::class, 'index'], [RouteTerminableMW::class]);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/v1/route-test';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $request = new Request();
        $response = new Response();

        $router->dispatch($request, $response);
        $this->assertTrue($state->terminated, 'terminate() should fire on route middleware');
    }

    public function test_multiple_middleware_all_terminate(): void
    {
        $states = [
            new class { public bool $terminated = false; },
            new class { public bool $terminated = false; },
        ];

        $container = new Container();
        $container->singleton(MWFirst::class, fn() => new MWFirst($states[0]));
        $container->singleton(MWSecond::class, fn() => new MWSecond($states[1]));

        $router = new Router($container);
        $router->middleware([MWFirst::class, MWSecond::class]);
        $router->get('/multi', [TerminableController::class, 'index'], []);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/multi';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $request = new Request();
        $response = new Response();

        $router->dispatch($request, $response);
        $this->assertTrue($states[0]->terminated, 'first middleware should terminate');
        $this->assertTrue($states[1]->terminated, 'second middleware should terminate');
    }

    public function test_middleware_without_terminate_no_error(): void
    {
        $container = new Container();
        $container->singleton(NonTerminableMW::class, fn() => new NonTerminableMW());

        $router = new Router($container);
        $router->middleware([NonTerminableMW::class]);
        $router->get('/no-term', [TerminableController::class, 'index'], []);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/no-term';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $request = new Request();
        $response = new Response();

        $router->dispatch($request, $response);
        // TerminableController::index returns '' which is valid — middleware should still run without error
        $this->assertSame(200, $response->getStatusCode());
    }
}

class TerminableController
{
    public function index()
    {
        return '';
    }
}

class TerminableMW implements MiddlewareInterface
{
    use \App\Core\TerminableMiddleware;

    public function __construct(private readonly object $state) {}

    public function handle(Request $request, Response $response, callable $next): Response
    {
        return $next($request, $response);
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->state->terminated = true;
    }
}

class RouteTerminableMW implements MiddlewareInterface
{
    use \App\Core\TerminableMiddleware;

    public function __construct(private readonly object $state) {}

    public function handle(Request $request, Response $response, callable $next): Response
    {
        return $next($request, $response);
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->state->terminated = true;
    }
}

class MWFirst implements MiddlewareInterface
{
    use \App\Core\TerminableMiddleware;

    public function __construct(private readonly object $state) {}

    public function handle(Request $request, Response $response, callable $next): Response
    {
        return $next($request, $response);
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->state->terminated = true;
    }
}

class MWSecond implements MiddlewareInterface
{
    use \App\Core\TerminableMiddleware;

    public function __construct(private readonly object $state) {}

    public function handle(Request $request, Response $response, callable $next): Response
    {
        return $next($request, $response);
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->state->terminated = true;
    }
}

class NonTerminableMW implements MiddlewareInterface
{
    public function handle(Request $request, Response $response, callable $next): Response
    {
        return $next($request, $response);
    }
}
