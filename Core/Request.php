<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Interfaces\RequestInterface;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\UriInterface;

class Request extends ServerRequest implements RequestInterface
{
    protected array $bodyParams = [];
    protected array $queryParams = [];
    protected array $attributes = [];

    public function __construct(?string $method = null, ?UriInterface $uri = null, array $headers = [], $body = null, string $version = '1.1', array $serverParams = [])
    {
        if ($method !== null) {
            // Test mode: construct from provided parameters
            parent::__construct($method, $uri ?? new \GuzzleHttp\Psr7\Uri('/'), $headers, $body, $version, $serverParams);
            $this->queryParams = [];
            $this->bodyParams = [];
            return;
        }

        // Read globals at construction time
        $this->queryParams = $_GET ?? [];
        $this->bodyParams = $this->detectBodyParams();

        // Build PSR-7 request from current globals
        $psrRequest = \GuzzleHttp\Psr7\ServerRequest::fromGlobals();

        parent::__construct(
            $psrRequest->getMethod(),
            $psrRequest->getUri(),
            $psrRequest->getHeaders(),
            $psrRequest->getBody(),
            $psrRequest->getProtocolVersion(),
            $psrRequest->getServerParams()
        );
    }

    /**
     * Get the request URI path as a string (custom, non-PSR-7).
     */
    public function getUriPath(): string
    {
        return $this->getPath();
    }

    public function getPath(): string
    {
        return parent::getUri()->getPath();
    }

    public function getQuery(?string $key = null, mixed $default = null): mixed
    {
        $uriQuery = parent::getUri()->getQuery();
        $urlParams = $uriQuery !== '' ? $this->parseQueryString($uriQuery) : [];
        $query = array_merge($this->queryParams, $urlParams);
        if ($key === null) return $query;
        return $query[$key] ?? $default;
    }

    /**
     * Parse a query string into an array.
     */
    protected function parseQueryString(string $qs): array
    {
        $result = [];
        if ($qs === '') return $result;
        foreach (explode('&', $qs) as $part) {
            if (str_contains($part, '=')) {
                [$k, $v] = explode('=', $part, 2);
                $result[urldecode($k)] = urldecode($v);
            } else {
                $result[urldecode($part)] = '';
            }
        }
        return $result;
    }

    public function getPost(?string $key = null, mixed $default = null): mixed
    {
        $post = $this->getParsedBody();
        if ($key === null) return $post ?? [];
        return is_array($post) ? ($post[$key] ?? $default) : $default;
    }

    public function getFiles(?string $key = null): mixed
    {
        $files = $this->getUploadedFiles();
        if ($key === null) return $files;
        return $files[$key] ?? null;
    }

    public function input(string $key, $default = null)
    {
        // Check custom attributes first
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }

        // Check query params (merged from $_GET and URL query string)
        $query = $this->getQuery();
        if (array_key_exists($key, $query)) {
            return $query[$key];
        }

        // Check post body
        $post = $this->getPost();
        if (is_array($post) && array_key_exists($key, $post)) {
            return $post[$key];
        }

        // Check body params
        if (array_key_exists($key, $this->bodyParams)) {
            return $this->bodyParams[$key];
        }

        return $default;
    }

    public function getBodyParams(): array
    {
        return $this->bodyParams;
    }

    protected function detectBodyParams(): array
    {
        $contentType = $this->getHeaderLine('Content-Type');

        if (stripos($contentType, 'application/json') !== false) {
            $raw = (string)$this->getBody();
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }

        $parsedBody = $this->getParsedBody();
        if (is_array($parsedBody)) {
            return $parsedBody;
        }

        return [];
    }

    /**
     * Get a single header value as a string (alias for getHeaderLine).
     */
    public function header(string $name): ?string
    {
        return $this->getHeaderLine($name);
    }

    /**
     * Set an attribute (custom, not PSR-7 immutable).
     */
    public function setAttribute($key, $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Get an attribute — checks custom attributes first, then PSR-7.
     */
    public function getAttribute($key, $default = null): mixed
    {
        // First check our custom attributes
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }
        // Fall back to PSR-7 attributes
        return parent::getAttribute($key, $default);
    }

    /**
     * Alias for getAttribute() — legacy name.
     */
    public function getCustomAttribute($key, $default = null): mixed
    {
        return $this->getAttribute($key, $default);
    }
}
