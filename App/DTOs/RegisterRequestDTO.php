<?php
declare(strict_types=1);

namespace App\DTOs;

class RegisterRequestDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly string $roleId,
        public readonly ?string $storeId      = null,
        public readonly string $storeScope   = 'all',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name:       trim($data['name'] ?? ''),
            email:      trim($data['email'] ?? ''),
            password:   $data['password'] ?? '',
            roleId:     $data['role_id'] ?? 'cashier',
            storeId:    $data['store_id'] ?? null,
            storeScope: $data['store_scope'] ?? 'all',
        );
    }
}
