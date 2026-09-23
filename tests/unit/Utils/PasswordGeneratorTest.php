<?php

namespace Tests\Unit\Utils;

use Tests\TestCase;
use App\Utils\PasswordGenerator;

/**
 * Test password generation functionality
 */
class PasswordGeneratorTest extends TestCase
{
    /**
     * Test generating a random password
     */
    public function testGenerateRandomPassword(): void
    {
        $password = PasswordGenerator::generate(12);

        $this->assertIsString($password);
        $this->assertEquals(12, strlen($password));
        $this->assertMatchesRegularExpression('/[A-Z]/', $password, 'Password should contain uppercase');
        $this->assertMatchesRegularExpression('/[a-z]/', $password, 'Password should contain lowercase');
        $this->assertMatchesRegularExpression('/[0-9]/', $password, 'Password should contain numbers');
        $this->assertMatchesRegularExpression('/[!@#$%^&*]/', $password, 'Password should contain special chars');
    }

    /**
     * Test minimum length requirement
     */
    public function testMinimumLengthRequirement(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Password length must be at least 8 characters');

        PasswordGenerator::generate(7);
    }

    /**
     * Test generating multiple unique passwords
     */
    public function testGeneratesUniquePasswords(): void
    {
        $password1 = PasswordGenerator::generate(12);
        $password2 = PasswordGenerator::generate(12);

        $this->assertNotEquals($password1, $password2, 'Generated passwords should be unique');
    }

    /**
     * Test password strength
     */
    public function testPasswordStrength(): void
    {
        $password = PasswordGenerator::generate(16);

        // Should have good entropy
        $this->assertGreaterThanOrEqual(16, strlen($password));
        
        // Should have mixed character types
        $hasUpper = preg_match('/[A-Z]/', $password);
        $hasLower = preg_match('/[a-z]/', $password);
        $hasNumber = preg_match('/[0-9]/', $password);
        $hasSpecial = preg_match('/[!@#$%^&*]/', $password);

        $this->assertTrue($hasUpper && $hasLower && $hasNumber && $hasSpecial,
            'Password should have all character types');
    }

    /**
     * Test generating memorable password
     */
    public function testGenerateMemorablePassword(): void
    {
        $password = PasswordGenerator::generateMemorable(4);

        $this->assertIsString($password);
        $this->assertGreaterThanOrEqual(4, str_word_count($password));
    }

    /**
     * Test memorable password minimum words
     */
    public function testMemorablePasswordMinimumWords(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Word count must be at least 3');

        PasswordGenerator::generateMemorable(2);
    }
}
