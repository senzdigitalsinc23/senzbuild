<?php
declare(strict_types=1);

namespace App\Core\Health;

class DiskSpaceHealthCheck implements HealthCheckInterface
{
    public function getName(): string
    {
        return 'disk_space';
    }

    public function check(): array
    {
        $path = dirname(__DIR__, 3) . '/storage';
        if (!is_dir($path)) {
            $path = sys_get_temp_dir();
        }

        $freeSpace  = @disk_free_space($path);
        $totalSpace = @disk_total_space($path);

        if ($freeSpace === false || $totalSpace === false) {
            return [
                'status'  => 'warning',
                'message' => 'Disk space check skipped (path unavailable)',
            ];
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
            'status'      => $status,
            'message'     => $message,
            'free_space'  => round($freeSpace / 1024 / 1024 / 1024, 2) . ' GB',
            'total_space' => round($totalSpace / 1024 / 1024 / 1024, 2) . ' GB',
            'used_percent' => $usedPercent,
        ];
    }
}
