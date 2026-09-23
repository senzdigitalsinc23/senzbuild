<?php
declare(strict_types=1);

namespace App\DTOs;

class UserUpdateDTO
{
    public function __construct(
        public readonly ?string $username = null,
        public readonly ?string $email = null,
        public readonly ?string $status = null,
        public readonly ?int $roleId = null,
        public readonly ?array $extraPermissions = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            username: $data['username'] ?? null,
            email:    $data['email'] ?? null,
            status:   $data['status'] ?? null,
            roleId:   isset($data['role_id']) ? (int)$data['role_id'] : null,
            extraPermissions: $data['extra_permissions'] ?? null,
        );
    }

    public function getChangedFields(): array
    {
        return array_filter(get_object_vars($this), fn($v) => $v !== null);
    }
}
