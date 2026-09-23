<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\JsonSchemaValidator;

class JsonSchemaValidatorTest extends TestCase
{
    public function test_valid_object(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'id'   => ['type' => 'integer'],
                'name' => ['type' => 'string'],
            ],
            'required' => ['id', 'name'],
        ];
        $errors = JsonSchemaValidator::validate(['id' => 1, 'name' => 'Test'], $schema);
        $this->assertSame([], $errors);
    }

    public function test_missing_required_field(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'id'   => ['type' => 'integer'],
                'name' => ['type' => 'string'],
            ],
            'required' => ['id', 'name'],
        ];
        $errors = JsonSchemaValidator::validate(['id' => 1], $schema);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('name', $errors[0]);
    }

    public function test_wrong_type(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'id'   => ['type' => 'integer'],
                'name' => ['type' => 'string'],
            ],
        ];
        $errors = JsonSchemaValidator::validate(['id' => 'not-an-int', 'name' => 'Test'], $schema);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('integer', $errors[0]);
    }

    public function test_nested_object(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'user' => [
                    'type' => 'object',
                    'properties' => [
                        'id'   => ['type' => 'integer'],
                        'name' => ['type' => 'string'],
                    ],
                    'required' => ['id', 'name'],
                ],
            ],
        ];
        $errors = JsonSchemaValidator::validate(['user' => ['id' => 1, 'name' => 'Alice']], $schema);
        $this->assertSame([], $errors);
    }

    public function test_array_of_items(): void
    {
        $schema = [
            'type'  => 'array',
            'items' => ['type' => 'integer'],
        ];
        $errors = JsonSchemaValidator::validate([1, 2, 3], $schema);
        $this->assertSame([], $errors);

        $errors = JsonSchemaValidator::validate([1, 'two', 3], $schema);
        $this->assertCount(1, $errors);
    }

    public function test_string_constraints(): void
    {
        $schema = [
            'type'      => 'string',
            'minLength' => 3,
            'maxLength' => 10,
        ];
        $errors = JsonSchemaValidator::validate('ab', $schema);
        $this->assertCount(1, $errors);

        $errors = JsonSchemaValidator::validate('a-b-c-d-e-f', $schema);
        $this->assertCount(1, $errors);

        $errors = JsonSchemaValidator::validate('abc', $schema);
        $this->assertSame([], $errors);
    }

    public function test_number_constraints(): void
    {
        $schema = [
            'type'    => 'number',
            'minimum' => 0,
            'maximum' => 100,
        ];
        $errors = JsonSchemaValidator::validate(-1, $schema);
        $this->assertCount(1, $errors);

        $errors = JsonSchemaValidator::validate(101, $schema);
        $this->assertCount(1, $errors);

        $errors = JsonSchemaValidator::validate(50, $schema);
        $this->assertSame([], $errors);
    }

    public function test_enum_constraint(): void
    {
        $schema = [
            'type' => 'string',
            'enum' => ['active', 'inactive', 'pending'],
        ];
        $errors = JsonSchemaValidator::validate('unknown', $schema);
        $this->assertCount(1, $errors);

        $errors = JsonSchemaValidator::validate('active', $schema);
        $this->assertSame([], $errors);
    }

    public function test_email_format(): void
    {
        $schema = [
            'type'   => 'string',
            'format' => 'email',
        ];
        $errors = JsonSchemaValidator::validate('not-an-email', $schema);
        $this->assertCount(1, $errors);

        $errors = JsonSchemaValidator::validate('test@example.com', $schema);
        $this->assertSame([], $errors);
    }

    public function test_uuid_format(): void
    {
        $schema = [
            'type'   => 'string',
            'format' => 'uuid',
        ];
        $errors = JsonSchemaValidator::validate('not-a-uuid', $schema);
        $this->assertCount(1, $errors);

        $errors = JsonSchemaValidator::validate('550e8400-e29b-41d4-a716-446655440000', $schema);
        $this->assertSame([], $errors);
    }

    public function test_additional_properties_rejected(): void
    {
        $schema = [
            'type'                 => 'object',
            'properties'           => ['id' => ['type' => 'integer']],
            'additionalProperties' => false,
        ];
        $errors = JsonSchemaValidator::validate(['id' => 1, 'extra' => 'nope'], $schema);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('extra', $errors[0]);
    }

    public function test_array_size_constraints(): void
    {
        $schema = [
            'type'     => 'array',
            'items'    => ['type' => 'string'],
            'minItems' => 1,
            'maxItems' => 3,
        ];
        $errors = JsonSchemaValidator::validate([], $schema);
        $this->assertCount(1, $errors);

        $errors = JsonSchemaValidator::validate(['a', 'b', 'c', 'd'], $schema);
        $this->assertCount(1, $errors);

        $errors = JsonSchemaValidator::validate(['a', 'b'], $schema);
        $this->assertSame([], $errors);
    }

    public function test_pattern_constraint(): void
    {
        $schema = [
            'type'    => 'string',
            'pattern' => '/^[A-Z]{2}\\d{4}$/',
        ];
        $errors = JsonSchemaValidator::validate('abc123', $schema);
        $this->assertCount(1, $errors);

        $errors = JsonSchemaValidator::validate('US1234', $schema);
        $this->assertSame([], $errors);
    }

    public function test_null_value(): void
    {
        $schema = [
            'type'      => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'age'  => ['type' => 'integer', 'nullable' => true],
            ],
        ];
        // nullable is not a standard keyword we enforce — null passes type check for any type
        $errors = JsonSchemaValidator::validate(['name' => 'Test', 'age' => null], $schema);
        // null doesn't match integer type, but we don't have a special nullable rule
        $this->assertCount(1, $errors);
    }
}
