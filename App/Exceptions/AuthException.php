<?php
declare(strict_types=1);

namespace App\Exceptions;

/**
 * Exception thrown for authentication and authorization errors
 */
class AuthException extends BaseException
{
    protected int $statusCode = 401;
    protected string $errorCode = 'AUTH_ERROR';

    public function __construct(
        string $message = "Authentication error",
        ?int $statusCode = null,
        ?string $errorCode = null,
        array $context = [],
        ?\Exception $previous = null
    ) {
        parent::__construct(
            message: $message,
            statusCode: $statusCode,
            errorCode: $errorCode,
            context: $context,
            previous: $previous
        );
    }

    public static function invalidCredentials(): self
    {
        return new self(
            message: "Invalid email or password",
            statusCode: 401,
            errorCode: 'INVALID_CREDENTIALS'
        );
    }

    public static function emailAlreadyExists(string $email): self
    {
        return new self(
            message: "Email {$email} is already registered",
            statusCode: 409,
            errorCode: 'EMAIL_ALREADY_EXISTS',
            context: ['email' => $email]
        );
    }

    public static function registrationFailed(): self
    {
        return new self(
            message: "User registration failed",
            statusCode: 500,
            errorCode: 'REGISTRATION_FAILED'
        );
    }

    public static function accountInactive(): self
    {
        return new self(
            message: "Account is inactive. Please contact the administrator.",
            statusCode: 403,
            errorCode: 'ACCOUNT_INACTIVE'
        );
    }

    public static function userNotFound(): self
    {
        return new self(
            message: "User not found",
            statusCode: 404,
            errorCode: 'USER_NOT_FOUND'
        );
    }

    public static function invalidCurrentPassword(): self
    {
        return new self(
            message: "Current password is incorrect",
            statusCode: 400,
            errorCode: 'INVALID_CURRENT_PASSWORD'
        );
    }

    public static function passwordUpdateFailed(): self
    {
        return new self(
            message: "Failed to update password",
            statusCode: 500,
            errorCode: 'PASSWORD_UPDATE_FAILED'
        );
    }

    public static function tokenExpired(): self
    {
        return new self(
            message: "Token has expired",
            statusCode: 401,
            errorCode: 'TOKEN_EXPIRED'
        );
    }

    public static function invalidToken(): self
    {
        return new self(
            message: "Invalid token",
            statusCode: 401,
            errorCode: 'INVALID_TOKEN'
        );
    }

    public static function invalidRefreshToken(): self
    {
        return new self(
            message: "Invalid or expired refresh token",
            statusCode: 401,
            errorCode: 'INVALID_REFRESH_TOKEN'
        );
    }

    public static function unauthorized(): self
    {
        return new self(
            message: "Unauthorized access",
            statusCode: 401,
            errorCode: 'UNAUTHORIZED'
        );
    }

    public static function accountLocked(?int $minutesRemaining = null): self
    {
        if ($minutesRemaining === null) {
            return new self(
                message: "Account is locked by administrator. Please contact support.",
                statusCode: 423,
                errorCode: 'ACCOUNT_LOCKED'
            );
        }
        
        return new self(
            message: "Account is temporarily locked due to multiple failed login attempts. Please try again in {$minutesRemaining} minutes or contact System Administrator for help.",
            statusCode: 423,
            errorCode: 'ACCOUNT_LOCKED_TEMPORARY',
            context: ['minutes_remaining' => $minutesRemaining]
        );
    }
}
