<?php
declare(strict_types=1);

namespace App\Core\Interfaces;

use App\Core\Container;

interface ServiceProviderInterface
{
    /**
     * Register bindings in the container.
     *
     * @param Container $app The application container
     * @return void
     */
    public function register(Container $app): void;
}
