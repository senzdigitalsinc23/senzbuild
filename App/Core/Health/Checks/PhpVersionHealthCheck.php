<?php
declare(strict_types=1);

namespace App\Core\Health\Checks;

use App\Core\Health\HealthCheckInterface;

class PhpVersionHealthCheck implements HealthCheckInterface
{
    public function getName(): string
    {
        return 'php';
    }

    public function check(): array
    {
        $version = PHP_VERSION;
        $requiredVersion = '8.0.0';
        $status = version_compare($version, $requiredVersion, '>=') ? 'healthy' : 'warning';

        return [
            'status' => $status,
            'message' => "PHP version: {$version}",
            'version' => $version,
            'required' => $requiredVersion,
        ];
    }
}
