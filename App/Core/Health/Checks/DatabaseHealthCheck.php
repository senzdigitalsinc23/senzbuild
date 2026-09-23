<?php
declare(strict_types=1);

namespace App\Core\Health\Checks;

use App\Core\Health\HealthCheckInterface;
use PDO;

class DatabaseHealthCheck implements HealthCheckInterface
{
    public function __construct(private PDO $db) {}

    public function getName(): string
    {
        return 'database';
    }

    public function check(): array
    {
        try {
            $stmt = $this->db->query('SELECT 1 as health_check');
            $result = $stmt->fetch();

            if ($result && $result['health_check'] == 1) {
                return [
                    'status' => 'healthy',
                    'message' => 'Database connection successful',
                    'provider' => \App\Core\Config::get('database.driver', 'mysql'),
                    'version' => $this->db->getAttribute(PDO::ATTR_SERVER_VERSION),
                ];
            }

            return [
                'status' => 'unhealthy',
                'message' => 'Database query returned unexpected result',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Database connection failed',
                'error' => $e->getMessage(),
            ];
        }
    }
}
