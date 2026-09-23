<?php
declare(strict_types=1);

namespace App\Exceptions;

/**
 * Exception thrown when validation fails
 * 
 * Provides detailed validation errors for each field
 */
class ValidationException extends BaseException
{
    protected int $statusCode = 422;
    protected string $errorCode = 'VALIDATION_FAILED';
    protected array $errors;

    public function __construct(
        array $errors,
        string $message = "Validation failed",
        ?int $statusCode = null,
        ?\Exception $previous = null
    ) {
        $this->errors = $errors;
        parent::__construct(
            message: $message,
            statusCode: $statusCode,
            context: ['errors' => $errors],
            previous: $previous
        );
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public static function withErrors(array $errors): self
    {
        return new self($errors);
    }

    /**
     * Create for a single field error
     */
    public static function field(string $field, string $message): self
    {
        return new self([$field => [$message]]);
    }

    /**
     * Override toArray to include errors at top level
     */
    public function toArray(): array
    {
        $data = parent::toArray();
        $data['errors'] = $this->errors;
        return $data;
    }
}
