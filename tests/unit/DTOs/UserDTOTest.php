<?php

namespace Tests\Unit\DTOs;

use App\DTOs\UserDTO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(UserDTO::class)]
class UserDTOTest extends TestCase
{
    #[Test]
    public function from_array_creates_dto(): void
    {
        $dto = UserDTO::fromArray([
            'id'        => '1',
            'name'      => 'Test User',
            'email'     => 'test@example.com',
            'password'  => 'hashed_password',
            'role_id'   => 'general_manager',
            'store_scope' => 'all',
            'is_active' => 1,
        ]);

        $this->assertSame('1', $dto->id);
        $this->assertSame('Test User', $dto->name);
        $this->assertSame('test@example.com', $dto->email);
        $this->assertTrue($dto->isActive);
        $this->assertSame('general_manager', $dto->roleId);
    }

    #[Test]
    public function from_array_defaults_is_active_to_true_when_missing(): void
    {
        $dto = UserDTO::fromArray([
            'id'       => '1',
            'name'     => 'User',
            'email'    => 'u@example.com',
            'password' => 'pwd',
            'role_id'  => 'cashier',
            'store_scope' => 'all',
        ]);
        $this->assertTrue($dto->isActive);
    }

    #[Test]
    public function from_array_defaults_role_to_cashier(): void
    {
        $dto = UserDTO::fromArray([
            'id'       => '1',
            'name'     => 'User',
            'email'    => 'u@example.com',
            'password' => 'pwd',
        ]);
        $this->assertSame('cashier', $dto->roleId);
    }

    #[Test]
    public function from_array_defaults_store_scope_to_all(): void
    {
        $dto = UserDTO::fromArray([
            'id'       => '1',
            'name'     => 'User',
            'email'    => 'u@example.com',
            'password' => 'pwd',
        ]);
        $this->assertSame('all', $dto->storeScope);
    }

    #[Test]
    public function toArray_contains_expected_keys(): void
    {
        $dto = UserDTO::fromArray([
            'id'        => '1',
            'name'      => 'Test',
            'email'     => 't@t.com',
            'password'  => 'pwd',
            'role_id'   => 'cashier',
            'store_scope' => 'all',
            'is_active' => 1,
        ]);

        $arr = $dto->toArray();
        $this->assertArrayHasKey('id', $arr);
        $this->assertArrayHasKey('name', $arr);
        $this->assertArrayHasKey('email', $arr);
        $this->assertArrayHasKey('role_id', $arr);
        $this->assertArrayHasKey('is_active', $arr);
        $this->assertArrayNotHasKey('password', $arr);
    }

    #[Test]
    public function toArrayWithoutPassword_does_not_include_password(): void
    {
        $dto = UserDTO::fromArray([
            'id'       => '1',
            'name'     => 'Test',
            'email'    => 't@t.com',
            'password' => 'secret',
            'role_id'  => 'cashier',
            'store_scope' => 'all',
            'is_active' => 1,
        ]);

        $arr = $dto->toArrayWithoutPassword();
        $this->assertArrayNotHasKey('password', $arr);
    }
}
