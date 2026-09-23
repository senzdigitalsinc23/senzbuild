<?php
declare(strict_types=1);

namespace App\Core\Health;

interface HealthCheckInterface
{
    /**
     * The name of the health check (e.g., 'database', 'redis', 'stripe_api')
     */
    public function getName(): string;

    /**
     * Perform the health check and return the result.
     *
     * Result format:
     * [
     *   'status' => 'healthy' | 'warning' | 'unhealthy',
     *   'message' => 'Human readable status message',
     *   'details' => [ ... optional additional data ... ]
     * ]
     */
    public function check(): array;
}
