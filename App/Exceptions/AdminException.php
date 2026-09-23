<?php
declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class AdminException extends Exception
{
    public function __construct(string $message = "Admin operation failed", int $code = 500, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function userNotFound(int $id): self
    {
        return new self("User with ID {$id} not found", 404);
    }

    public static function emailAlreadyExists(string $email): self
    {
        return new self("Email {$email} is already in use", 409);
    }

    public static function userUpdateFailed(): self
    {
        return new self("Failed to update user", 500);
    }

    public static function userDeleteFailed(): self
    {
        return new self("Failed to delete user", 500);
    }

    public static function insufficientPermissions(): self
    {
        return new self("Insufficient permissions for this operation", 403);
    }

    public static function invalidUserData(): self
    {
        return new self("Invalid user data provided", 400);
    }
}
