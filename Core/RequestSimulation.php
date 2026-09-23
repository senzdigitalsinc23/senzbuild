<?php
declare(strict_types=1);
namespace App\Core;

class RequestSimulation extends Request
{
    protected array $session = [];

    public function __construct(
        string $method,
        string $uri,
        array $query = [],
        array $body = [],
        array $headers = [],
        array $session = []
    ) {
        $this->method = strtoupper($method);
        $this->uri = $uri;
        $this->queryParams = $query;
        $this->bodyParams = $body;
        $this->headers = $headers;
        $this->session = $session;
    }

    public function input(string $key, $default = null)
    {
        // Prioritize JSON body if Content-Type is JSON
        if (!empty($this->headers['Content-Type']) && $this->headers['Content-Type'] === 'application/json') {
            $data = json_decode(json_encode($this->bodyParams), true);
            return $data[$key] ?? $default;
        }

        return $this->bodyParams[$key] ?? $this->queryParams[$key] ?? $default;
    }

    public function session(string $key, $default = null)
    {
        return $this->session[$key] ?? $default;
    }
}
