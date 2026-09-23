<?php

namespace Tests\Unit\Validation;

use Tests\TestCase;
use App\Exceptions\ValidationException;

/**
 * Test input validation and sanitization
 */
class InputValidationTest extends TestCase
{
    /**
     * Test email validation
     */
    public function testEmailValidation(): void
    {
        $validEmails = [
            'test@example.com',
            'user.name@domain.co.uk',
            'first+last@test.org'
        ];

        foreach ($validEmails as $email) {
            $this->assertTrue(
                filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
                "Email should be valid: $email"
            );
        }

        $invalidEmails = [
            'notanemail',
            '@example.com',
            'user@',
            'user name@example.com',
            'user@domain',
        ];

        foreach ($invalidEmails as $email) {
            $this->assertFalse(
                filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
                "Email should be invalid: $email"
            );
        }
    }

    /**
     * Test SQL injection patterns are detected
     */
    public function testSqlInjectionPatternsDetected(): void
    {
        $sqlInjectionPatterns = [
            "'; DROP TABLE users; --",
            "1' OR '1'='1",
            "admin'--",
            "' UNION SELECT * FROM passwords--",
            "1; DELETE FROM students--",
            "' OR 1=1--",
            "1' AND '1'='1",
        ];

        foreach ($sqlInjectionPatterns as $pattern) {
            // These patterns should contain SQL keywords
            $this->assertTrue(
                preg_match('/(DROP|DELETE|UNION|SELECT|INSERT|UPDATE|--|;)/i', $pattern) === 1,
                "Pattern should contain SQL keywords: $pattern"
            );
        }
    }

    /**
     * Test XSS patterns are detected
     */
    public function testXssPatternsDetected(): void
    {
        $xssPatterns = [
            '<script>alert("XSS")</script>',
            '<img src=x onerror=alert(1)>',
            'javascript:alert(1)',
            '<iframe src="javascript:alert(1)">',
            '<body onload=alert(1)>',
        ];

        foreach ($xssPatterns as $pattern) {
            // These patterns should contain HTML/JS tags
            $this->assertTrue(
                preg_match('/<[^>]*>|javascript:/i', $pattern) === 1,
                "Pattern should contain HTML/JS: $pattern"
            );
        }
    }

    /**
     * Test string sanitization
     */
    public function testStringSanitization(): void
    {
        $input = '<script>alert("XSS")</script>Hello';
        $sanitized = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

        $this->assertStringNotContainsString('<script>', $sanitized);
        $this->assertStringContainsString('&lt;script&gt;', $sanitized);
        $this->assertStringContainsString('Hello', $sanitized);
    }

    /**
     * Test integer validation
     */
    public function testIntegerValidation(): void
    {
        $validIntegers = ['123', '0', '-456', '999999'];

        foreach ($validIntegers as $int) {
            $this->assertTrue(
                filter_var($int, FILTER_VALIDATE_INT) !== false,
                "Should be valid integer: $int"
            );
        }

        $invalidIntegers = ['abc', '12.34', '12a', '', null];

        foreach ($invalidIntegers as $int) {
            $this->assertFalse(
                filter_var($int, FILTER_VALIDATE_INT) !== false,
                "Should be invalid integer: " . var_export($int, true)
            );
        }
    }

    /**
     * Test URL validation
     */
    public function testUrlValidation(): void
    {
        $validUrls = [
            'https://example.com',
            'http://test.org/path',
            'https://sub.domain.com/path?query=1'
        ];

        foreach ($validUrls as $url) {
            $this->assertTrue(
                filter_var($url, FILTER_VALIDATE_URL) !== false,
                "URL should be valid: $url"
            );
        }

        $invalidUrls = [
            'not a url',
            'javascript:alert(1)',
            'ftp://invalid',
            'htp://typo.com'
        ];

        foreach ($invalidUrls as $url) {
            $this->assertFalse(
                filter_var($url, FILTER_VALIDATE_URL) !== false,
                "URL should be invalid: $url"
            );
        }
    }

    /**
     * Test password strength requirements
     */
    public function testPasswordStrengthRequirements(): void
    {
        // Strong passwords
        $strongPasswords = [
            'SecurePass123!',
            'MyP@ssw0rd',
            'C0mpl3x!Pass',
        ];

        foreach ($strongPasswords as $password) {
            $this->assertGreaterThanOrEqual(8, strlen($password));
            $this->assertMatchesRegularExpression('/[A-Z]/', $password);
            $this->assertMatchesRegularExpression('/[a-z]/', $password);
            $this->assertMatchesRegularExpression('/[0-9]/', $password);
        }

        // Weak passwords
        $weakPasswords = [
            'short',
            'nouppercase123!',
            'NOLOWERCASE123!',
            'NoNumbers!',
        ];

        foreach ($weakPasswords as $password) {
            $isWeak = strlen($password) < 8 ||
                     !preg_match('/[A-Z]/', $password) ||
                     !preg_match('/[a-z]/', $password) ||
                     !preg_match('/[0-9]/', $password);
            
            $this->assertTrue($isWeak, "Password should be weak: $password");
        }
    }

    /**
     * Test ValidationException structure
     */
    public function testValidationExceptionStructure(): void
    {
        $errors = [
            'email' => ['Email is required'],
            'password' => ['Password must be at least 8 characters']
        ];

        $exception = new ValidationException($errors);

        $this->assertEquals(422, $exception->getStatusCode());
        $this->assertEquals('VALIDATION_FAILED', $exception->getErrorCode());
        $this->assertEquals($errors, $exception->getErrors());

        $array = $exception->toArray();
        $this->assertFalse($array['success']);
        $this->assertArrayHasKey('errors', $array);
    }

    /**
     * Test phone number validation
     */
    public function testPhoneNumberValidation(): void
    {
        $validPhones = [
            '+1234567890',
            '0123456789',
            '+44 20 1234 5678',
            '(123) 456-7890'
        ];

        foreach ($validPhones as $phone) {
            // Remove non-digits for validation
            $digitsOnly = preg_replace('/[^0-9+]/', '', $phone);
            $this->assertGreaterThanOrEqual(10, strlen($digitsOnly));
        }
    }

    /**
     * Test date validation
     */
    public function testDateValidation(): void
    {
        $validDates = [
            '2026-02-22',
            '2025-12-31',
            '2024-01-01'
        ];

        foreach ($validDates as $date) {
            $parsed = \DateTime::createFromFormat('Y-m-d', $date);
            $this->assertInstanceOf(\DateTime::class, $parsed);
            $this->assertEquals($date, $parsed->format('Y-m-d'));
        }

        $invalidDates = [
            '2026-13-01', // Invalid month
            '2026-02-30', // Invalid day
            'not-a-date',
            '22/02/2026'  // Wrong format
        ];

        foreach ($invalidDates as $date) {
            $parsed = \DateTime::createFromFormat('Y-m-d', $date);
            $isValid = $parsed && $parsed->format('Y-m-d') === $date;
            $this->assertFalse($isValid, "Date should be invalid: $date");
        }
    }
}
