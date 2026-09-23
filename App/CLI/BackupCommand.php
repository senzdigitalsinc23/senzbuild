<?php
declare(strict_types=1);

namespace App\CLI;

use App\Services\BackupService;
use App\Services\BackupStorageService;

/**
 * BackupCommand — CLI command for manual backups.
 *
 * Usage:
 *   php cli.php backup:run                    (backup to all active storage)
 *   php cli.php backup:run --storage <id>     (backup to specific storage)
 *   php cli.php backup:list                   (list backup history)
 */
class BackupCommand extends Command
{
    protected string $name = 'backup:run';
    protected string $description = 'Run database backup to configured storage';

    public function handle(array $args): void
    {
        $storageId = $this->getOption($args, '--storage');

        $storageService = new BackupStorageService();
        $backupService  = new BackupService();

        if ($storageId) {
            // Backup to specific storage
            $this->info("Running backup to storage: {$storageId}");
            try {
                $result = $backupService->runBackup($storageId);
                $this->success("Backup completed: {$result['filename']} ({$result['size_bytes']} bytes, {$result['tables_count']} tables)");
            } catch (\Throwable $e) {
                $this->error("Backup failed: " . $e->getMessage());
            }
        } else {
            // Backup to all active storage
            $storages = $storageService->listAll();
            $activeStorages = array_filter($storages, fn($s) => $s['is_active']);

            if (empty($activeStorages)) {
                $this->warning("No active storage configured. Add storage via Settings > Backup.");
                return;
            }

            foreach ($activeStorages as $storage) {
                $this->info("Backing up to: {$storage['name']} ({$storage['type']})");
                try {
                    $result = $backupService->runBackup($storage['id']);
                    $this->success("  ✓ {$result['filename']} ({$result['size_bytes']} bytes)");
                } catch (\Throwable $e) {
                    $this->error("  ✗ Failed: " . $e->getMessage());
                }
            }
        }
    }

    private function getOption(array $args, string $name): ?string
    {
        $index = array_search($name, $args);
        if ($index === false) return null;
        return $args[$index + 1] ?? null;
    }
}
