<?php
declare(strict_types=1);

namespace App\Exceptions;

/**
 * Exception thrown when a resource conflict occurs (e.g., duplicate entry)
 */
class ConflictException extends BaseException
{
    protected int $statusCode = 409;
    protected string $errorCode = 'CONFLICT';

    /**
     * Create for duplicate resource
     */
    public static function duplicate(string $resourceType, string $identifier): self
    {
        return new self(
            message: "{$resourceType} already exists: {$identifier}",
            errorCode: 'DUPLICATE_RESOURCE',
            context: [
                'resource_type' => $resourceType,
                'identifier' => $identifier
            ]
        );
    }

    /**
     * Create for resource already in use
     */
    public static function inUse(string $resourceType, string $identifier, string $usedBy = ''): self
    {
        $message = "{$resourceType} is already in use: {$identifier}";
        if ($usedBy) {
            $message .= " (used by: {$usedBy})";
        }

        return new self(
            message: $message,
            errorCode: 'RESOURCE_IN_USE',
            context: [
                'resource_type' => $resourceType,
                'identifier' => $identifier,
                'used_by' => $usedBy
            ]
        );
    }

    /**
     * Create with custom message
     */
    public static function withMessage(string $message): self
    {
        return new self($message);
    }
}
