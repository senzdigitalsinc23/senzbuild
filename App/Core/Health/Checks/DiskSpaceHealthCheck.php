<?php
declare(strict_types=1);

namespace App\Core\Health\Checks;

use App\Core\Health\HealthCheckInterface;

class DiskSpaceHealthCheck implements HealthCheckInterface
{
    public function getName(): string
    {
        return 'disk';
    }

    public function check(): array
    {
        try {
            $path = __DIR__ . '/../../../../storage';
            $freeSpace  = @disk_free_space($path);
            $totalSpace = @disk_total_space($path);

            if ($freeSpace === false || $totalSpace === false) {
                $root = PHP_OS_FAMILY === 'Windows' ? 'C:\\' : '/';
                $freeSpace  = @disk_free_space($root);
                $totalSpace = @disk_total_space($root);
            }

            if ($freeSpace === false || $totalSpace === false) {
                return ['status' => 'healthy', 'message' => 'Disk space check skipped (path unavailable)'];
            }

            $usedPercent = round((($totalSpace - $freeSpace) / $totalSpace) * 100, 2);

            $status = 'healthy';
            $message = "Disk usage: {$usedPercent}%";

            if ($usedPercent >= 95) {
                $status = 'unhealthy';
                $message = "Critical: Disk usage at {$usedPercent}%";
            } elseif ($usedPercent >= 90) {
                $status = 'warning';
                $message = "Warning: Disk usage at {$usedPercent}%";
            }

            return [
                'status' => $status,
                'message' => $message,
                'used_percent' => $usedPercent
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'warning',
                'message' => 'Disk space check failed',
                'error' => $e->getMessage(),
            ];
        }
    }
}
