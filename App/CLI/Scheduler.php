<?php
declare(strict_types=1);

namespace App\CLI;

use App\Core\Database;
use App\Core\Queue;
use PDO;

/**
 * Scheduler — Built-in daemon that dispatches scheduled jobs without cron.
 *
 * Runs continuously, checking system_settings to see if each scheduled task
 * is due to run. When a task is due, it dispatches the corresponding job
 * to the queue (via Queue::dispatch) and updates the last-run timestamp.
 *
 * Usage:
 *   php cli.php schedule:run              (daemon mode — runs forever)
 *   php cli.php schedule:run --once        (run once, check & dispatch)
 *   php cli.php schedule:run --once --force (force-run all tasks now)
 *
 * For production, run this alongside queue:work:
 *   php cli.php schedule:run &
 *   php cli.php queue:work
 *
 * Schedule configs are stored in system_settings (group='scheduler'):
 *   scheduler_enabled          = "1"
 *   scheduler_loyalty_time     = "06:00"   (time of day to run loyalty)
 *   scheduler_credits_time     = "00:00"   (time of day to run credit expiry)
 *   scheduler_loyalty_last_run = "2026-06-05 06:00:00"  (auto-tracked)
 *   scheduler_credits_last_run = "2026-06-05 00:00:00"  (auto-tracked)
 */
class Scheduler extends Command
{
    protected string $name = 'schedule:run';
    protected string $description = 'Run the built-in task scheduler daemon';

    private PDO $db;

    /** @var array<string, array{job_class: string, time_key: string, last_run_key: string, description: string}> */
    private array $tasks = [];

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();

        // Define the tasks the scheduler manages
        $this->tasks = [
            'loyalty' => [
                'job_class'    => \App\Jobs\ProcessLoyaltyJob::class,
                'time_key'     => 'scheduler_loyalty_time',
                'last_run_key' => 'scheduler_loyalty_last_run',
                'description'  => 'Loyalty points expiration & birthday bonuses',
            ],
            'credits' => [
                'job_class'    => \App\Jobs\ExpireStoreCreditsJob::class,
                'time_key'     => 'scheduler_credits_time',
                'last_run_key' => 'scheduler_credits_last_run',
                'description'  => 'Store credit expiration',
            ],
            'backup' => [
                'job_class'    => \App\Jobs\BackupJob::class,
                'time_key'     => null,  // Uses backup_schedules table
                'last_run_key' => null,
                'description'  => 'Database backup to configured storage',
                'uses_own_schedule' => true,
                'run_inline'   => true, // Runs inside the scheduler, not via queue
            ],
        ];
    }

    public function handle(array $args): void
    {
        $runOnce = in_array('--once', $args, true);
        $force   = in_array('--force', $args, true);

        if ($force && !$runOnce) {
            $this->warning('--force requires --once. Ignoring --force.');
            $force = false;
        }

        $this->info("Scheduler started. Tasks: " . implode(', ', array_keys($this->tasks)));
        if ($force) {
            $this->warning('--force mode: running all tasks immediately regardless of schedule.');
        }

        while (true) {
            $enabled = $this->getSetting('scheduler_enabled', '1');

            if ($enabled !== '1') {
                if (!$runOnce) {
                    $this->info('Scheduler is disabled (scheduler_enabled != 1). Sleeping 60s...');
                    sleep(60);
                    continue;
                } else {
                    $this->warning('Scheduler is disabled. No tasks will run.');
                    break;
                }
            }

            $dispatchedAny = false;

            foreach ($this->tasks as $taskId => $task) {
                if ($this->isTaskDue($taskId, $task, $force)) {
                    $this->info("Running {$taskId}: {$task['description']}");
                    
                    if ($task['run_inline'] ?? false) {
                        // Execute the job directly within the scheduler process
                        // (backup jobs are too important to rely on a separate queue worker)
                        try {
                            $jobInstance = new $task['job_class']();
                            if (method_exists($jobInstance, 'handle')) {
                                $jobInstance->handle();
                            }
                        } catch (\Throwable $e) {
                            $this->error("{$taskId} job failed: " . $e->getMessage());
                        }
                    } else {
                        // Standard path: dispatch to queue for async processing
                        Queue::dispatch($task['job_class'], []);
                    }
                    
                    $this->updateLastRun($taskId, $task);
                    $dispatchedAny = true;
                }
            }

            if ($runOnce) {
                if (!$dispatchedAny && !$force) {
                    $this->info('No tasks due right now.');
                }
                break;
            }

            // Sleep 60 seconds between checks in daemon mode
            sleep(60);
        }
    }

    /**
     * Check whether a task is due to run.
     */
    private function isTaskDue(string $taskId, array $task, bool $force): bool
    {
        if ($force) {
            return true;
        }

        // Backup task uses its own schedule table
        if ($task['uses_own_schedule'] ?? false) {
            return $this->isBackupDue();
        }

        $scheduledTime = $this->getSetting($task['time_key'], '06:00');
        $lastRun       = $this->getSetting($task['last_run_key'], '');

        // Parse scheduled time (e.g., "06:00")
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $scheduledTime, $m)) {
            return false; // Invalid time format
        }
        $scheduledHour   = (int)$m[1];
        $scheduledMinute = (int)$m[2];

        // Current time
        $now = new \DateTimeImmutable('now');
        $currentHour   = (int)$now->format('H');
        $currentMinute = (int)$now->format('i');

        // Build today's scheduled DateTime
        $scheduledToday = \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i',
            $now->format('Y-m-d') . " {$scheduledHour}:{$scheduledMinute}"
        );

        if (!$scheduledToday) {
            return false;
        }

        // If last run is after the scheduled time today, already ran
        if ($lastRun) {
            $lastRunDt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $lastRun);
            if ($lastRunDt && $lastRunDt >= $scheduledToday) {
                return false; // Already ran today
            }
        }

        // Due if current time >= scheduled time
        return $now >= $scheduledToday;
    }

    /**
     * Check if any backup schedule is due.
     */
    private function isBackupDue(): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM backup_schedules
            WHERE is_active = 1
              AND next_run_at IS NOT NULL
              AND next_run_at <= NOW()
        ");
        $stmt->execute();
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Update the last-run timestamp for a task.
     */
    private function updateLastRun(string $taskId, array $task): void
    {
        // Backup task manages its own schedule in backup_schedules table
        if ($task['uses_own_schedule'] ?? false) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->upsertSetting($task['last_run_key'], $now);
    }

    // ── Settings helpers ────────────────────────────────────────────────────

    /**
     * Get a system_setting value, returning $default if not found.
     */
    private function getSetting(string $key, string $default = ''): string
    {
        $stmt = $this->db->prepare("SELECT `value` FROM system_settings WHERE `key` = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['value'] : $default;
    }

    /**
     * Insert or update a system_setting value.
     */
    private function upsertSetting(string $key, string $value): void
    {
        $this->db->prepare(
            "INSERT INTO system_settings (`key`, `value`, `group`) VALUES (?, ?, 'scheduler')
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = NOW()"
        )->execute([$key, $value]);
    }
}
