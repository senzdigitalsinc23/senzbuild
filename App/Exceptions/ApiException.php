<?php
declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Throwable;

class ApiException extends Exception
{
    protected int $statusCode;
    protected ?string $errorCode;

    public function __construct(
        string $message = "An API error occurred",
        int $statusCode = 400,
        ?string $errorCode = null,
        Throwable $previous = null
    ) {
        $this->statusCode = $statusCode;
        $this->errorCode = $errorCode;
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }
}
