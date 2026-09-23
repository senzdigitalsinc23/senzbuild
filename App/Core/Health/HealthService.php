<?php
declare(strict_types=1);

namespace App\Core\Health;

class HealthService
{
    /** @var HealthCheckInterface[] */
    private array $checks = [];

    public function registerCheck(HealthCheckInterface $check): void
    {
        $this->checks[$check->getName()] = $check;
    }

    public function runAll(): array
    {
        $results = [];
        $overallStatus = 'healthy';

        foreach ($this->checks as $name => $check) {
            $res = $check->check();
            $results[$name] = $res;

            if ($res['status'] === 'unhealthy') {
                $overallStatus = 'unhealthy';
            } elseif ($res['status'] === 'warning' && $overallStatus === 'healthy') {
                $overallStatus = 'warning';
            }
        }

        return [
            'status' => $overallStatus,
            'checks' => $results,
        ];
    }
}
