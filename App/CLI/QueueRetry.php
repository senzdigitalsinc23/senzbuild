<?php
declare(strict_types=1);

namespace App\CLI;

use App\Core\Config;
use PDO;

class QueueRetry extends Command
{
    protected string $name = 'queue:retry';
    protected string $description = 'Retry all failed jobs or a specific failed job ID.';

    private PDO $db;

    public function handle(array $args): void
    {
        $this->db = \App\Core\Database::getInstance()->getConnection();
        $table = Config::get('queue.connections.database.table', 'queue_jobs');

        $jobId = $args[0] ?? null;

        if ($jobId) {
            $this->retrySingleJob((int)$jobId, $table);
        } else {
            $this->retryAllFailed($table);
        }
    }

    private function retrySingleJob(int $jobId, string $table): void
    {
        $stmt = $this->db->prepare("SELECT * FROM {$table} WHERE id = :id AND status = 'failed'");
        $stmt->execute([':id' => $jobId]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$job) {
            $this->error("Failed job ID {$jobId} not found or not in failed status.");
            return;
        }

        $stmt = $this->db->prepare(
            "UPDATE {$table} SET status = 'pending', attempts = 0, reserved_at = NULL, available_at = :now, error_message = NULL, updated_at = NOW() WHERE id = :id"
        );
        $stmt->execute([':id' => $jobId, ':now' => time()]);
        $this->success("Job ID {$jobId} has been re-queued.");
    }

    private function retryAllFailed(string $table): void
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$table} WHERE status = 'failed'");
        $stmt->execute();
        $count = (int)$stmt->fetchColumn();

        if ($count === 0) {
            $this->info("No failed jobs to retry.");
            return;
        }

        $stmt = $this->db->prepare(
            "UPDATE {$table} SET status = 'pending', attempts = 0, reserved_at = NULL, available_at = :now, error_message = NULL, updated_at = NOW() WHERE status = 'failed'"
        );
        $stmt->execute([':now' => time()]);
        $this->success("{$count} failed job(s) re-queued.");
    }
}
