<?php
declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * Base exception class for all application exceptions
 * 
 * Provides consistent error handling with HTTP status codes,
 * error codes, and additional context data.
 */
abstract class BaseException extends Exception
{
    /**
     * Additional context data for the exception
     */
    protected array $context = [];

    /**
     * HTTP status code for the exception
     */
    protected int $statusCode = 500;

    /**
     * Error code for client identification
     */
    protected string $errorCode = 'INTERNAL_ERROR';

    /**
     * @param string $message Error message
     * @param int|null $statusCode HTTP status code (defaults to class default)
     * @param string|null $errorCode Error code (defaults to class default)
     * @param array $context Additional context data
     * @param Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message = "",
        ?int $statusCode = null,
        ?string $errorCode = null,
        array $context = [],
        ?Throwable $previous = null
    ) {
        $this->statusCode = $statusCode ?? $this->statusCode;
        $this->errorCode = $errorCode ?? $this->errorCode;
        $this->context = $context;
        
        parent::__construct($message, $this->statusCode, $previous);
    }

    /**
     * Get HTTP status code
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get error code
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Get context data
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Set context data
     */
    public function setContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }

    /**
     * Add context data
     */
    public function addContext(string $key, mixed $value): self
    {
        $this->context[$key] = $value;
        return $this;
    }

    /**
     * Convert exception to array for JSON response
     */
    public function toArray(): array
    {
        $data = [
            'success' => false,
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
                'status' => $this->statusCode,
            ]
        ];

        if (!empty($this->context)) {
            $data['error']['details'] = $this->context;
        }

        // Include trace in development mode
        if (($_ENV['APP_DEBUG'] ?? false) === true || ($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
            $data['error']['trace'] = $this->getTrace();
            $data['error']['file'] = $this->getFile();
            $data['error']['line'] = $this->getLine();
        }

        return $data;
    }

    /**
     * Convert exception to JSON string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }
}
