<?php
declare(strict_types=1);

namespace App\Exceptions;

/**
 * Exception thrown when access to a resource is forbidden
 */
class ForbiddenException extends BaseException
{
    protected int $statusCode = 403;
    protected string $errorCode = 'FORBIDDEN';

    /**
     * Create for insufficient permissions
     */
    public static function insufficientPermissions(string $requiredPermission = ''): self
    {
        $message = "You do not have permission to perform this action";
        if ($requiredPermission) {
            $message .= " (required: {$requiredPermission})";
        }

        return new self(
            message: $message,
            errorCode: 'INSUFFICIENT_PERMISSIONS',
            context: $requiredPermission ? ['required_permission' => $requiredPermission] : []
        );
    }

    /**
     * Create for account locked
     */
    public static function accountLocked(string $reason = ''): self
    {
        $message = "Account is locked";
        if ($reason) {
            $message .= ": {$reason}";
        }

        return new self(
            message: $message,
            errorCode: 'ACCOUNT_LOCKED',
            context: $reason ? ['reason' => $reason] : []
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
