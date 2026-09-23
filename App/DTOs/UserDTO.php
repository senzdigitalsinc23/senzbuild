<?php
declare(strict_types=1);

namespace App\DTOs;

class UserDTO
{
    public function __construct(
        public readonly string  $id,
        public readonly string  $name,
        public readonly string  $email,
        public readonly string  $password,
        public readonly string  $roleId,
        public readonly string  $storeScope,
        public readonly bool    $isActive,
        public readonly ?string $storeId          = null,
        public readonly ?string $lastLogin        = null,
        public readonly ?string $createdAt        = null,
        public readonly array   $extraPermissions = [],
    ) {}

    public static function fromArray(array $data): self
    {
        // extra_permissions stored as JSON string in DB
        $extra = [];
        if (!empty($data['extra_permissions'])) {
            $decoded = is_array($data['extra_permissions'])
                ? $data['extra_permissions']
                : json_decode($data['extra_permissions'], true);
            $extra = is_array($decoded) ? $decoded : [];
        }

        return new self(
            id:               $data['id']          ?? '',
            name:             $data['name']        ?? '',
            email:            $data['email']       ?? '',
            password:         $data['password']    ?? '',
            roleId:           $data['role_id']     ?? 'cashier',
            storeScope:       $data['store_scope'] ?? 'all',
            isActive:         (bool)($data['is_active'] ?? true),
            storeId:          $data['store_id']    ?? null,
            lastLogin:        $data['last_login']  ?? null,
            createdAt:        $data['created_at']  ?? null,
            extraPermissions: $extra,
        );
    }

    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'email'            => $this->email,
            'role_id'          => $this->roleId,
            'store_scope'      => $this->storeScope,
            'is_active'        => $this->isActive,
            'store_id'         => $this->storeId,
            'last_login'       => $this->lastLogin,
            'created_at'       => $this->createdAt,
            'extra_permissions'=> $this->extraPermissions,
        ];
    }

    public function toArrayWithoutPassword(): array
    {
        $data = $this->toArray();
        unset($data['password']);
        return $data;
    }
}
