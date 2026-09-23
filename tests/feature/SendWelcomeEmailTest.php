<?php
namespace App\Core;

abstract class HttpTestCase extends TestCase
{
    protected Container $container;

    public function __construct()
    {
        $this->container = new Container();
    }

    /**
     * Simulate a GET request
     */
    protected function get(string $uri, array $query = []): Response
    {
        $request = new RequestSimulation('GET', $uri, $query);
        return $this->dispatch($request);
    }

    /**
     * Simulate a POST request
     */
    protected function post(string $uri, array $body = []): Response
    {
        $request = new RequestSimulation('POST', $uri, [], $body);
        return $this->dispatch($request);
    }

    /**
     * Dispatch the simulated request to the router
     */
    protected function dispatch(RequestSimulation $request): Response
    {
        $router = $this->container->resolve(Router::class);
        return $router->dispatch($request);
    }
}
