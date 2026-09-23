<?php
declare(strict_types=1);

namespace App\GraphQL;

/**
 * Schema — collection of named types.
 */
class Schema
{
    /** @var array<string, Type> */
    public array $types = [];

    public function __construct(
        public readonly string $queryType,
        public ?string         $mutationType = null,
        array                  $types      = [],
    ) {
        foreach ($types as $type) {
            $this->addType($type);
        }
    }

    public function addType(Type $type): void
    {
        $this->types[$type->name] = $type;
    }

    public function getType(string $name): ?Type
    {
        return $this->types[$name] ?? null;
    }

    public function hasType(string $name): bool
    {
        return isset($this->types[$name]);
    }
}
