<?php
declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class StudentException extends Exception
{
    public function __construct(string $message = "Student operation failed", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function notFound(string $identifier): self
    {
        return new self("Student not found: {$identifier}", 404);
    }

    public static function alreadyExists(string $studentNo): self
    {
        return new self("Student already exists: {$studentNo}", 409);
    }

    public static function validationFailed(array $errors): self
    {
        $message = "Validation failed: " . implode(', ', $errors);
        return new self($message, 422);
    }

    public static function databaseError(string $operation): self
    {
        return new self("Database error during {$operation}", 500);
    }

    public static function importFailed(string $reason): self
    {
        return new self("Import failed: {$reason}", 400);
    }
}
