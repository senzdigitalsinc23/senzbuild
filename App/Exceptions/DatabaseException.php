<?php
declare(strict_types=1);

namespace App\Exceptions;

use PDOException;
use Throwable;

/**
 * Exception thrown when a database operation fails
 */
class DatabaseException extends BaseException
{
    protected int $statusCode = 500;
    protected string $errorCode = 'DATABASE_ERROR';

    /**
     * Create from PDOException
     */
    public static function fromPDO(PDOException $e, string $operation = 'database operation'): self
    {
        // Don't expose sensitive database details in production
        $message = ($_ENV['APP_DEBUG'] ?? false) 
            ? "Database error during {$operation}: " . $e->getMessage()
            : "An error occurred during {$operation}";

        return new self(
            message: $message,
            context: [
                'operation' => $operation,
                'sql_state' => $e->getCode()
            ],
            previous: $e
        );
    }

    /**
     * Create for a specific operation
     */
    public static function operation(string $operation, ?Throwable $previous = null): self
    {
        return new self(
            message: "Database error during {$operation}",
            context: ['operation' => $operation],
            previous: $previous
        );
    }

    /**
     * Create for connection failure
     */
    public static function connectionFailed(?Throwable $previous = null): self
    {
        return new self(
            message: "Failed to connect to database",
            errorCode: 'DATABASE_CONNECTION_FAILED',
            previous: $previous
        );
    }

    /**
     * Create for transaction failure
     */
    public static function transactionFailed(string $reason = '', ?Throwable $previous = null): self
    {
        $message = "Transaction failed" . ($reason ? ": {$reason}" : '');
        return new self(
            message: $message,
            errorCode: 'TRANSACTION_FAILED',
            previous: $previous
        );
    }
}
