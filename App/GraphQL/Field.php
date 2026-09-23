<?php
declare(strict_types=1);

namespace App\GraphQL;

/**
 * Schema field definition.
 */
class Field
{
    /** @var mixed */
    public $resolve;

    /** @var Arg[] */
    public array $args = [];

    public function __construct(
        public readonly string $type,
        mixed                  $resolve = null,
    ) {
        $this->resolve = $resolve;
    }

    /**
     * Add arguments to this field (fluent builder).
     *
     * @param Arg ...$args
     */
    public function args(Arg ...$args): self
    {
        $this->args = array_merge($this->args, $args);
        return $this;
    }
}

