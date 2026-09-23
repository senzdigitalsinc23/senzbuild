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
        $this->session = $session;
        $psrUri = new \GuzzleHttp\Psr7\Uri($uri);
        if (!empty($query)) {
            $psrUri = $psrUri->withQuery(http_build_query($query));
        }
        $psrHeaders = [];
        foreach ($headers as $name => $value) {
            $psrHeaders[$name] = (array) $value;
        }
        parent::__construct(strtoupper($method), $psrUri, $psrHeaders, null, '1.1', []);
        $this->queryParams = $query;
        $this->bodyParams = $body;
    }

    public function input(string $key, $default = null)
    {
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
