<?php
declare(strict_types=1);

namespace App\Core\Interfaces;

use Psr\Container\ContainerInterface as PsrContainerInterface;

/**
 * ContainerInterface
 *
 * This interface defines the contract for the dependency injection container.
 * It extends PSR-11 to ensure compatibility with the broader PHP ecosystem.
 */
interface ContainerInterface extends PsrContainerInterface
{
    /**
     * Binds a factory to an abstract name.
     */
    public function bind(string $abstract, callable $factory): void;

    /**
     * Binds a singleton to an abstract name.
     */
    public function singleton(string $abstract, callable $factory): void;
}
