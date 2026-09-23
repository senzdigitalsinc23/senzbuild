<?php
declare(strict_types=1);

namespace App\CLI;

use PDO;

class QueuePrune extends Command
{
    protected string $name = 'queue:prune';
    protected string $description = 'Remove expired entries from the failed jobs (DLQ) table.';

    private PDO $db;

    public function handle(array $args): void
    {
        $this->db = \App\Core\Database::getInstance()->getConnection();

        $olderThan = (int)($args[0] ?? 604800); // default 7 days in seconds

        $stmt = $this->db->prepare(
            "DELETE FROM queue_failed_jobs WHERE failed_at < DATE_SUB(NOW(), INTERVAL :seconds SECOND)"
        );
        $stmt->execute([':seconds' => $olderThan]);
        $deleted = $stmt->rowCount();

        $this->success("Pruned {$deleted} expired entry/entries from the DLQ (older than {$olderThan}s).");
    }
}
