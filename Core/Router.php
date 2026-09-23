<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Interfaces\ContainerInterface;
use App\Core\Interfaces\RequestInterface;
use App\Core\Interfaces\ResponseInterface;
use Closure;
use Exception;

class Router
{
    protected array $routes = [];
    protected ContainerInterface $container;
    protected array $globalMiddleware = [];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function get(string $uri, callable|array $action, array $middleware = [], ?string $requestClass = null): void
    {
        $this->addRoute('GET', $uri, $action, $middleware, [], $requestClass);
    }

    public function post(string $uri, callable|array $action, array $middleware = [], ?string $requestClass = null): void
    {
        $this->addRoute('POST', $uri, $action, $middleware, [], $requestClass);
    }

    public function getApi(string $version, string $uri, callable|array $action, array $middleware = [], ?string $requestClass = null): void
    {
        $this->addRoute('GET', "/api/{$version}{$uri}", $action, $middleware, [], $requestClass);
    }

    public function postApi(string $version, string $uri, callable|array $action, array $middleware = [], ?string $requestClass = null): void
    {
        $this->addRoute('POST', "/api/{$version}{$uri}", $action, $middleware, [], $requestClass);
    }

    public function putApi(string $version, string $uri, callable|array $action, array $middleware = [], ?string $requestClass = null): void
    {
        $this->addRoute('PUT', "/api/{$version}{$uri}", $action, $middleware, [], $requestClass);
    }

    public function deleteApi(string $version, string $uri, callable|array $action, array $middleware = [], ?string $requestClass = null): void
    {
        $this->addRoute('DELETE', "/api/{$version}{$uri}", $action, $middleware, [], $requestClass);
    }

    public function middleware(array $middleware): void
    {
        $this->globalMiddleware = array_merge($this->globalMiddleware, $middleware);
    }

    public function put(string $uri, callable|array $action, array $middleware = [], ?string $requestClass = null): void
    {
        $this->addRoute('PUT', $uri, $action, $middleware, [], $requestClass);
    }

    public function delete(string $uri, callable|array $action, array $middleware = [], ?string $requestClass = null): void
    {
        $this->addRoute('DELETE', $uri, $action, $middleware, [], $requestClass);
    }

    protected function addRoute(string $method, string $uri, callable|array $action, array $middleware = [], array $docs = [], ?string $requestClass = null): void
    {
        $regex = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $uri);
        $regex = '#^' . $regex . '$#';

        $this->routes[] = [
            'method'     => $method,
            'uri'        => $uri,
            'regex'      => $regex,
            'action'     => $action,
            'middleware' => $middleware,
            'docs'       => $docs,
            'request'    => $requestClass,
            'bindings'   => []
        ];
    }

    /**
     * Register a model binding for a route parameter.
     */
    public function bind(string $param, string $modelClass, string $keyColumn = 'id'): void
    {
        ModelBinder::bind($param, $modelClass, $keyColumn);
    }

    /**
     * Check if a cached route file exists and auto-load it.
     */
    public function loadCachedRoutes(): void
    {
        if (RouteCache::hasCache()) {
            RouteCache::load($this);
        }
    }

    public function dispatch(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Auto-load cached routes if available
        if (!env('APP_DEBUG', false) && RouteCache::hasCache()) {
            RouteCache::load($this);
        }

        $method = $request->getMethod();
        $uri    = $request->getPath();

        // Find route
        $route = null;
        $params = [];

        foreach ($this->routes as $r) {
            if ($r['method'] === $method) {
                if ($r['uri'] === $uri) {
                    $route = $r;
                    break;
                } elseif (preg_match($r['regex'], $uri, $matches)) {
                    $route = $r;
                    // Extract named parameters from regex matches
                    foreach ($matches as $key => $value) {
                        if (is_string($key)) {
                            $params[$key] = $value;
                        }
                    }
                    break;
                }
            }
        }

        // For OPTIONS preflight requests...
        if (!$route && $method === 'OPTIONS') {
            foreach ($this->routes as $r) {
                if ($r['uri'] === $uri || preg_match($r['regex'], $uri)) {
                    $route = $r;
                    break;
                }
            }
        }

        if (!$route) {
            // 404: Route not found
            $response->setStatusCode(404);
            $response->setHeader('Content-Type', 'application/json');
            $response->setContent(json_encode(['success' => false, 'message' => 'Route not found']));
            return $response;
        }

        // Model binding — resolve bound parameters to model instances
        $params = ModelBinder::resolve($params, $request instanceof Request ? $request : null);

        // Request Validation Layer
        if (!empty($route['request'])) {
            $requestClass = $route['request'];
            $validator = $this->container->resolve($requestClass);

            if ($validator->fails()) {
                $response->setStatusCode(422);
                $response->setHeader('Content-Type', 'application/json');
                $response->setContent(json_encode([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors'  => $validator->errors()
                ]));
                return $response;
            }
        }

        // Build final controller callable
        $controllerHandler = function(RequestInterface $req, ResponseInterface $res) use ($route, $params) {
            $action = $route['action'];
            if (is_callable($action)) {
                $result = $action($req, $res, $params);
            } else {
                [$controllerClass, $actionMethod] = $action;
                $controller = $this->container->resolve($controllerClass);
                $result = $controller->$actionMethod($req, $res, $params);
            }

            if (is_string($result)) {
                $res->setContent($result);
                return $res;
            }

            if ($result instanceof Response) {
                return $result;
            }

            throw new Exception("Controller must return string or Response instance");
        };

        // Combine global middleware...
        $middlewares = array_merge($this->globalMiddleware, $route['middleware']);
        return $this->applyMiddleware($middlewares, $request, $response, $controllerHandler);
    }

    protected function applyMiddleware(array $middlewares, RequestInterface $request, ResponseInterface $response, callable $handler): ResponseInterface
    {
        $dispatcher = array_reduce(
            array_reverse($middlewares),
            function ($next, $middleware) use ($request, $response) {
                return function (RequestInterface $req, ResponseInterface $res) use ($next, $middleware, $request, $response) {
                    if (is_callable($middleware)) {
                        // Wrap $next so single-arg callers ($next($req)) still work
                        $wrappedNext = function (RequestInterface $r, ?ResponseInterface $s = null) use ($next, $response) {
                            return $next($r, $s ?? $response);
                        };
                        $ref = new \ReflectionFunction($middleware);
                        $params = $ref->getNumberOfParameters();
                        if ($params <= 2) {
                            $result = $middleware($req, $wrappedNext);
                        } else {
                            $result = $middleware($req, $res, $wrappedNext);
                        }
                        if ($result instanceof ResponseInterface) {
                            return $result;
                        }
                        return $next($req, $res);
                    } elseif (is_string($middleware)) {
                        $mw = $this->container->resolve($middleware);
                        $result = $mw->handle($req, $res, $next);
                        if (method_exists($mw, 'terminate')) {
                            $mw->terminate($req, $result);
                        }
                        return $result;
                    }
                    return $next($req, $res);
                };
            },
            $handler
        );

        return $dispatcher($request, $response);
    }

    /**
     * Placeholder for URI generation by name. Not implemented; returns null.
     */
    public function generateUri(string $name, array $params = []): ?string
    {
        return null;
    }

}
