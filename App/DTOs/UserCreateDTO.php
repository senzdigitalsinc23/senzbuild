<?php
declare(strict_types=1);

namespace App\DTOs;

class UserCreateDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly string $username = '',
        public readonly int $roleId = 1,
        public readonly string $status = 'active',
        public readonly string $userId = ''
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            email:    $data['email'] ?? throw new \InvalidArgumentException('Email is required'),
            password: $data['password'] ?? throw new \InvalidArgumentException('Password is required'),
            username: $data['username'] ?? $data['email'] ?? '',
            roleId:   (int)($data['role_id'] ?? 1),
            status:   $data['status'] ?? 'active',
            userId:   $data['user_id'] ?? uniqid('user_'),
        );
    }
}
