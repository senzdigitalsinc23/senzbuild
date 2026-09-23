<?php
declare(strict_types=1);

namespace App\Exceptions;

/**
 * Exception thrown when a request is malformed or invalid
 */
class BadRequestException extends BaseException
{
    protected int $statusCode = 400;
    protected string $errorCode = 'BAD_REQUEST';

    /**
     * Create for invalid input
     */
    public static function invalidInput(string $field, string $reason = ''): self
    {
        $message = "Invalid input for field: {$field}";
        if ($reason) {
            $message .= " ({$reason})";
        }

        return new self(
            message: $message,
            errorCode: 'INVALID_INPUT',
            context: [
                'field' => $field,
                'reason' => $reason
            ]
        );
    }

    /**
     * Create for missing required field
     */
    public static function missingField(string $field): self
    {
        return new self(
            message: "Required field missing: {$field}",
            errorCode: 'MISSING_REQUIRED_FIELD',
            context: ['field' => $field]
        );
    }

    /**
     * Create for invalid format
     */
    public static function invalidFormat(string $field, string $expectedFormat): self
    {
        return new self(
            message: "Invalid format for {$field}. Expected: {$expectedFormat}",
            errorCode: 'INVALID_FORMAT',
            context: [
                'field' => $field,
                'expected_format' => $expectedFormat
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
