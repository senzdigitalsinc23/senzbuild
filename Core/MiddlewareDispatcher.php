<?php
declare(strict_types=1);

namespace App\Core;

class MiddlewareDispatcher
{
    protected array $middleware = [];
    protected $controller;
    protected Container $container;
    protected int $index = 0;

    public function __construct(array $middleware, callable $controller, Container $container)
    {
        $this->middleware = $middleware;
        $this->controller = $controller;
        $this->container = $container;
    }

    public function dispatch(Request $request): Response
    {
        $middlewareStack = $this->middleware;
        $controller      = $this->controller;

        $next = function (Request $request) use (&$middlewareStack, &$controller, &$next) {
            if ($middleware = array_shift($middlewareStack)) {
                $middlewareInstance = new $middleware();
                return $middlewareInstance->handle($request, $next);
            }
            // Call actual controller
            return call_user_func($controller, $request);
        };

        return $next($request);
    }

     public function handle(Request $request, Response $response): Response
    {
        if (!isset($this->middleware[$this->index])) {
            // No more middleware, call controller
            $callback = $this->controller;
            return $callback($request, $response);
        }

        $middlewareClass = $this->middleware[$this->index];
        $this->index++;

        // Instantiate middleware with container for DI
        $middleware = $this->container->make($middlewareClass);

        if (!method_exists($middleware, 'handle')) {
            throw new \Exception("Middleware must have a handle method.");
        }

        // Call middleware handle with next callback
        return $middleware->handle($request, $response, function ($req, $res) {
            return $this->handle($req, $res);
        });
    }
}
