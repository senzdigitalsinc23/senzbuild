<?php

namespace Tests\Unit\DTOs;

use App\DTOs\LoginRequestDTO;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(LoginRequestDTO::class)]
class LoginRequestDTOTest extends TestCase
{
    #[Test]
    public function from_array_creates_dto_for_valid_data(): void
    {
        $dto = LoginRequestDTO::fromArray([
            'email'    => 'user@example.com',
            'password' => 'password123',
        ]);

        $this->assertSame('user@example.com', $dto->email);
        $this->assertSame('password123', $dto->password);
    }

    #[Test]
    public function from_array_throws_for_missing_email(): void
    {
        $this->expectException(ValidationException::class);
        LoginRequestDTO::fromArray([
            'password' => 'password123',
        ]);
    }

    #[Test]
    public function from_array_throws_for_missing_password(): void
    {
        $this->expectException(ValidationException::class);
        LoginRequestDTO::fromArray([
            'email' => 'user@example.com',
        ]);
    }

    #[Test]
    public function from_array_throws_for_empty_email(): void
    {
        $this->expectException(ValidationException::class);
        LoginRequestDTO::fromArray([
            'email'    => '',
            'password' => 'password123',
        ]);
    }

    #[Test]
    public function from_array_throws_for_invalid_email(): void
    {
        $this->expectException(ValidationException::class);
        LoginRequestDTO::fromArray([
            'email'    => 'not-an-email',
            'password' => 'password123',
        ]);
    }

    #[Test]
    public function from_array_throws_for_short_password(): void
    {
        $this->expectException(ValidationException::class);
        LoginRequestDTO::fromArray([
            'email'    => 'user@example.com',
            'password' => '12345',
        ]);
    }

    #[Test]
    public function from_array_returns_validation_errors(): void
    {
        try {
            LoginRequestDTO::fromArray(['email' => '', 'password' => '']);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('email', $errors);
            $this->assertArrayHasKey('password', $errors);
        }
    }

    #[Test]
    public function email_is_lowercased(): void
    {
        $dto = LoginRequestDTO::fromArray([
            'email'    => 'USER@Example.COM',
            'password' => 'password123',
        ]);
        $this->assertSame('user@example.com', $dto->email);
    }

    #[Test]
    public function password_preserves_original_case(): void
    {
        $dto = LoginRequestDTO::fromArray([
            'email'    => 'user@example.com',
            'password' => 'MixedCasePwd123!',
        ]);
        $this->assertSame('MixedCasePwd123!', $dto->password);
    }
}
