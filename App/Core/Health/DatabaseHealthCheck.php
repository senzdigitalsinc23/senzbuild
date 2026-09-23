<?php
declare(strict_types=1);

namespace App\Core\Health;

/**
 * Lazy Database Health Check — defers PDO resolution until check() is called.
 *
 * Prevents boot-time crashes when the database is unavailable.
 */
class DatabaseHealthCheck implements HealthCheckInterface
{
    private \App\Core\Container $container;
    private ?string $lastError = null;

    public function __construct(\App\Core\Container $container)
    {
        $this->container = $container;
    }

    public function getName(): string
    {
        return 'database';
    }

    public function check(): array
    {
        try {
            $pdo = $this->container->resolve(\PDO::class);
            $stmt = $pdo->query('SELECT 1');
            $this->lastError = null;
            return [
                'status'  => 'healthy',
                'message' => 'Database connection successful',
            ];
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return [
                'status'  => 'unhealthy',
                'message' => 'Database connection failed: ' . $e->getMessage(),
            ];
        }
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }
}
