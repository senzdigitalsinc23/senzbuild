<?php

namespace Tests\Unit\Exceptions;

use Tests\TestCase;
use App\Exceptions\ValidationException;

/**
 * Test validation exception functionality
 */
class ValidationExceptionTest extends TestCase
{
    /**
     * Test creating validation exception with errors
     */
    public function testCreateWithErrors(): void
    {
        $errors = [
            'email' => ['Email is required', 'Email must be valid'],
            'password' => ['Password is required']
        ];

        $exception = new ValidationException($errors);

        $this->assertEquals(422, $exception->getStatusCode());
        $this->assertEquals('VALIDATION_FAILED', $exception->getErrorCode());
        $this->assertEquals($errors, $exception->getErrors());
        $this->assertEquals('Validation failed', $exception->getMessage());
    }

    /**
     * Test creating validation exception with single field
     */
    public function testCreateForSingleField(): void
    {
        $exception = ValidationException::field('email', 'Email is required');

        $this->assertEquals(422, $exception->getStatusCode());
        $this->assertArrayHasKey('email', $exception->getErrors());
        $this->assertEquals(['Email is required'], $exception->getErrors()['email']);
    }

    /**
     * Test creating validation exception with withErrors factory
     */
    public function testCreateWithErrorsFactory(): void
    {
        $errors = ['name' => ['Name is required']];
        $exception = ValidationException::withErrors($errors);

        $this->assertEquals($errors, $exception->getErrors());
    }

    /**
     * Test exception to array conversion
     */
    public function testToArray(): void
    {
        $errors = ['email' => ['Email is required']];
        $exception = new ValidationException($errors);

        $array = $exception->toArray();

        $this->assertIsArray($array);
        $this->assertFalse($array['success']);
        $this->assertArrayHasKey('error', $array);
        $this->assertEquals('VALIDATION_FAILED', $array['error']['code']);
        $this->assertEquals(422, $array['error']['status']);
        $this->assertArrayHasKey('errors', $array);
        $this->assertEquals($errors, $array['errors']);
    }

    /**
     * Test exception to JSON conversion
     */
    public function testToJson(): void
    {
        $errors = ['email' => ['Email is required']];
        $exception = new ValidationException($errors);

        $json = $exception->toJson();

        $this->assertIsString($json);
        $data = json_decode($json, true);
        $this->assertIsArray($data);
        $this->assertFalse($data['success']);
        $this->assertEquals($errors, $data['errors']);
    }

    /**
     * Test context data
     */
    public function testContextData(): void
    {
        $errors = ['email' => ['Email is required']];
        $exception = new ValidationException($errors);

        $context = $exception->getContext();

        $this->assertIsArray($context);
        $this->assertArrayHasKey('errors', $context);
        $this->assertEquals($errors, $context['errors']);
    }
}
