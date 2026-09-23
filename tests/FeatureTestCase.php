<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Config;

abstract class FeatureTestCase extends TestCase
{
    protected Container $app;
    protected ?Response $lastResponse = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootApplication();
    }

    /**
     * Simulate the framework bootstrapping process.
     */
    protected function bootApplication(): void
    {
        // Load Config
        Config::load(dirname(__DIR__, 2) . '/config');

        // Initialize Container
        $this->app = new Container();

        // Register Modular Service Providers
        $this->app->register(\App\Providers\CoreServiceProvider::class);
        $this->app->register(\App\Providers\DatabaseServiceProvider::class);
        $this->app->register(\App\Providers\RepositoryServiceProvider::class);
        $this->app->register(\App\Providers\ServiceServiceProvider::class);
    }

    /**
     * Simulate a GET request.
     */
    protected function get(string $uri, array $headers = []): Response
    {
        return $this->dispatchRequest('GET', $uri, [], $headers);
    }

    /**
     * Simulate a POST request.
     */
    protected function post(string $uri, array $params = [], array $headers = []): Response
    {
        return $this->dispatchRequest('POST', $uri, $params, $headers);
    }

    /**
     * Simulate a PUT request.
     */
    protected function put(string $uri, array $params = [], array $headers = []): Response
    {
        return $this->dispatchRequest('PUT', $uri, $params, $headers);
    }

    /**
     * Simulate a DELETE request.
     */
    protected function delete(string $uri, array $headers = []): Response
    {
        return $this->dispatchRequest('DELETE', $uri, [], $headers);
    }

    /**
     * Core request dispatcher.
     */
    protected function dispatchRequest(string $method, string $uri, array $params = [], array $headers = []): Response
    {
        // 1. Mock the global state for the Request object
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['HTTP_ORIGIN'] = 'http://localhost:3000'; // Default for CORS tests

        // 2. Create Request and Response
        $request = new Request();

        // Inject params for POST/PUT
        if (!empty($params)) {
            $request->setBody($params);
        }

        $response = new Response();

        // 3. Setup Router
        $router = new Router($this->app);
        $routesPath = dirname(__DIR__, 2) . '/routes/web.php';
        if (file_exists($routesPath)) {
            require $routesPath;
        }
        $apiRoutesPath = dirname(__DIR__, 2) . '/routes/api.php';
        if (file_exists($apiRoutesPath)) {
            require $apiRoutesPath;
        }

        // 4. Dispatch and capture
        $this->lastResponse = $router->dispatch($request, $response);

        return $this->lastResponse;
    }

    /**
     * Assert that the response status code matches.
     */
    protected function assertStatus(int $expectedCode): void
    {
        $this->assertEquals($expectedCode, $this->lastResponse->getStatusCode(), "Expected status code {$expectedCode} but got {$this->lastResponse->getStatusCode()}");
    }

    /**
     * Assert that the response content matches a JSON structure.
     */
    protected function assertResponseJson(array $expected): void
    {
        $content = json_decode($this->lastResponse->getContent(), true);
        $this->assertEquals($expected, $content, "Response JSON does not match expected structure");
    }

    /**
     * Assert that a specific key in the JSON response has a specific value.
     */
    protected function assertJsonValue(string $key, mixed $expectedValue): void
    {
        $content = json_decode($this->lastResponse->getContent(), true);
        $this->assertArrayHasKey($key, $content, "JSON response is missing key: {$key}");
        $this->assertEquals($expectedValue, $content[$key], "Expected value for '{$key}' to be " . json_encode($expectedValue) . " but got " . json_encode($content[$key]));
    }
}
