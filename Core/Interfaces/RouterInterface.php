<?php
declare(strict_types=1);

namespace App\Core\Interfaces;

use App\Core\Interfaces\ContainerInterface;
use App\Core\Interfaces\RequestInterface;
use App\Core\Interfaces\ResponseInterface;

/**
 * RouterInterface
 *
 * Defines the contract for routing requests to controllers.
 */
interface RouterInterface
{
    public function get(string $uri, array $action, array $middleware = []): void;
    public function post(string $uri, array $action, array $middleware = []): void;
    public function put(string $uri, array $action, array $middleware = []): void;
    public function delete(string $uri, array $action, array $middleware = []): void;

    public function getApi(string $version, string $uri, array $action, array $middleware = []): void;
    public function postApi(string $version, string $uri, array $action, array $middleware = []): void;
    public function putApi(string $version, string $uri, array $action, array $middleware = []): void;
    public function deleteApi(string $version, string $uri, array $action, array $middleware = []): void;

    public function middleware(array $middleware): void;
    public function dispatch(RequestInterface $request, ResponseInterface $response): ResponseInterface;
    public function generateUri(string $name, array $params = []): ?string;
}
