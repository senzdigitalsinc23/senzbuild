<?php
declare(strict_types=1);

namespace App\Exceptions;

/**
 * Exception thrown when a requested resource is not found
 */
class NotFoundException extends BaseException
{
    protected int $statusCode = 404;
    protected string $errorCode = 'NOT_FOUND';

    /**
     * Create a not found exception for a specific resource
     */
    public static function resource(string $resourceType, string $identifier): self
    {
        return new self(
            message: "{$resourceType} not found: {$identifier}",
            context: [
                'resource_type' => $resourceType,
                'identifier' => $identifier
            ]
        );
    }

    /**
     * Create a not found exception with custom message
     */
    public static function withMessage(string $message): self
    {
        return new self($message);
    }
}
