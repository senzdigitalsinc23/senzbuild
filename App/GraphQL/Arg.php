<?php
declare(strict_types=1);

namespace App\GraphQL;

/**
 * Argument definition for a field.
 */
class Arg
{
    public function __construct(
        public readonly string  $name,
        public readonly string  $type,
        public mixed            $default = null,
        public bool             $required = false,
        public ?string          $description = null,
    ) {}
}
