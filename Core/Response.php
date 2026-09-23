<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Interfaces\ResponseInterface;
use GuzzleHttp\Psr7\Response as Psr7Response;

class Response extends Psr7Response implements ResponseInterface
{
    /**
     * PSR-7 Responses are immutable. To maintain compatibility with the
     * framework's current mutable API, we will store the state internally
     * and only finalize the PSR-7 response when send() is called.
     */
    private int $statusCode = 200;
    private array $headers = [];
    private string $content = '';
    private bool $isFinalized = false;

    public function __construct(string $content = '', int $statusCode = 200, array $headers = [])
    {
        parent::__construct($statusCode, $headers, $content);
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public function setStatusCode(int $code): void
    {
        $this->statusCode = $code;
        if ($this->isFinalized) {
            // If already finalized as a PSR-7 response, we can't easily change it
            // without returning a new instance. We'll stick to the internal state
            // for now to support the legacy API.
        }
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setHeader(string $key, string $value): void
    {
        $this->headers[$key] = $value;
    }

    /**
     * Get a custom header value set via setHeader().
     */
    public function getCustomHeader(string $key): ?string
    {
        return $this->headers[$key] ?? null;
    }

    public function hasHeader($key): bool
    {
        return isset($this->headers[$key]);
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $key => $value) {
            header("{$key}: {$value}");
        }

        echo $this->content;
        exit;
    }

    public function json(array $data, int $statusCode = 200): void
    {
        $this->jsonResponse($data, $statusCode);
        $this->send();
    }

    public function jsonResponse(array $data, int $statusCode = 200): self
    {
        $this->setStatusCode($statusCode);
        $this->setHeader('Content-Type', 'application/json');
        $this->setContent(json_encode($data));
        return $this;
    }

    public function jsonPaginated(
        array $data,
        int $total,
        int $page,
        int $perPage,
        string $message = 'Success',
        int $statusCode = 200
    ): void {
        $this->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'count' => count($data),
                'per_page' => $perPage,
                'current_page' => $page,
                'total_pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 0,
                'has_more' => ($page * $perPage) < $total,
            ],
        ], $statusCode);
    }
}
