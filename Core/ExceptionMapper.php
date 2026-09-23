<?php
declare(strict_types=1);

namespace App\Core;

use App\Exceptions\ApiException;
use Throwable;
use PDOException;
use InvalidArgumentException;
use RuntimeException;

class ExceptionMapper
{
    /**
     * Map common exceptions to API-friendly responses.
     */
    public function map(Throwable $e): ApiException
    {
        // If it's already an ApiException, return it as is
        if ($e instanceof ApiException) {
            return $e;
        }

        // Map specific custom application exceptions
        if ($e instanceof BaseException) {
            return new ApiException(
                $e->getMessage(),
                $e->getStatusCode(),
                $e->getErrorCode(),
                $e->getContext()
            );
        }

        // Map specific system exceptions to HTTP status codes
        return match (true) {
            $e instanceof PDOException => new ApiException(
                "Database error occurred",
                500,
                "DATABASE_ERROR"
            ),
            $e instanceof InvalidArgumentException => new ApiException(
                $e->getMessage(),
                400,
                "INVALID_ARGUMENT"
            ),
            $e instanceof RuntimeException => new ApiException(
                $e->getMessage(),
                500,
                "RUNTIME_ERROR"
            ),
            default => new ApiException(
                "An unexpected error occurred",
                500,
                "INTERNAL_SERVER_ERROR"
            ),
        };
    }
}
