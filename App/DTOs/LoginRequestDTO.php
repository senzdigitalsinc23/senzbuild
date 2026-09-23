<?php
declare(strict_types=1);

namespace App\DTOs;

use App\Exceptions\ValidationException;

class LoginRequestDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
    ) {}

    /**
     * @throws ValidationException
     */
    public static function fromArray(array $data): self
    {
        $email = trim((string)($data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');

        $errors = [];

        if ($email === '') {
            $errors['email'] = ['Email is required'];
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = ['Email is invalid'];
        }

        if ($password === '') {
            $errors['password'] = ['Password is required'];
        } elseif (strlen($password) < 8) {
            $errors['password'] = ['Password must be at least 8 characters'];
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        return new self(
            email: strtolower($email),
            password: $password,
        );
    }
}
