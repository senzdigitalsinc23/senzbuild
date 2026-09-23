<?php
declare(strict_types=1);
namespace App\Core;

abstract class HttpTestCase extends TestCase
{
    protected Container $container;

    public function __construct()
    {
        $this->container = new Container();
    }

    protected function get(string $uri, array $query = [], array $session = []): Response
    {
        $response = new Response();
        
        $request = new RequestSimulation('GET', $uri, $query, [], [], $session);
        return $this->dispatch($request, $response);
    }

    protected function post(string $uri, array $body = [], array $session = [], array $headers = []): Response
    {
        $response = new Response();
        $request = new RequestSimulation('POST', $uri, [], $body, $headers, $session);
        return $this->dispatch($request, $response);
    }

    protected function dispatch(RequestSimulation $request, ?Response $response = null): Response
    {
        $response ??= new Response();
        $router = $this->container->resolve(Router::class);
        return $router->dispatch($request, $response);
    }

    // JSON assertions
    protected function assertJsonContains(array $expected, array $actual, string $message = ''): void
    {
        foreach ($expected as $key => $value) {
            $this->assertTrue(
                isset($actual[$key]) && $actual[$key] === $value,
                $message ?: "JSON key '$key' does not match expected value"
            );
        }
    }
}
