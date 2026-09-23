<?php
declare(strict_types=1);

namespace App\CLI;

use PDO;

class QueueFailed extends Command
{
    protected string $name = 'queue:failed';
    protected string $description = 'List all failed jobs in the dead letter queue.';

    private PDO $db;

    public function handle(array $args): void
    {
        $this->db = \App\Core\Database::getInstance()->getConnection();

        $limit = (int)($args[0] ?? 20);

        $stmt = $this->db->prepare("SELECT id, uuid, connection, queue, exception, failed_at FROM queue_failed_jobs ORDER BY failed_at DESC LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($jobs)) {
            $this->info("No failed jobs found.");
            return;
        }

        echo "\n";
        foreach ($jobs as $job) {
            echo sprintf(
                "  ID: %s | Queue: %s | Failed: %s\n    Exception: %s\n\n",
                $job['uuid'],
                $job['queue'],
                $job['failed_at'],
                substr($job['exception'], 0, 120)
            );
        }
    }
}
