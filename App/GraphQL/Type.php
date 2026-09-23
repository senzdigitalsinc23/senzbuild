<?php
declare(strict_types=1);

namespace App\GraphQL;

/**
 * Schema type definition (Object, Scalar, Enum).
 */
class Type
{
    /** @var array<string, Field> */
    public array $fields = [];

    public function __construct(
        public readonly string  $name,
        public readonly string  $kind = 'object',
        public ?array           $enumValues = null,
    ) {}

    public function field(string $name, Field $field): self
    {
        $this->fields[$name] = $field;
        return $this;
    }

    public function hasField(string $name): bool
    {
        return isset($this->fields[$name]);
    }

    public function getField(string $name): Field
    {
        return $this->fields[$name];
    }
}
