<?php
declare(strict_types=1);

namespace App\CLI;

use App\Core\Config;
use PDO;

class QueueStats extends Command
{
    protected string $name = 'queue:stats';
    protected string $description = 'Show queue statistics: pending, reserved, failed counts.';

    private PDO $db;

    public function handle(array $args): void
    {
        $this->db = \App\Core\Database::getInstance()->getConnection();
        $table = Config::get('queue.connections.database.table', 'queue_jobs');
        $queue = $args[0] ?? Config::get('queue.connections.database.queue', 'default');

        $stmt = $this->db->prepare(
            "SELECT status, COUNT(*) as cnt FROM {$table} WHERE queue = :queue GROUP BY status"
        );
        $stmt->execute([':queue' => $queue]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $counts = ['pending' => 0, 'reserved' => 0, 'completed' => 0, 'failed' => 0];
        foreach ($rows as $row) {
            $counts[$row['status']] = (int)$row['cnt'];
        }

        $dlqStmt = $this->db->query("SELECT COUNT(*) FROM queue_failed_jobs");
        $dlqCount = (int)$dlqStmt->fetchColumn();

        echo "\n";
        echo "  Queue: {$queue}\n";
        echo "  Pending:   {$counts['pending']}\n";
        echo "  Reserved:  {$counts['reserved']}\n";
        echo "  Completed: {$counts['completed']}\n";
        echo "  Failed:    {$counts['failed']}\n";
        echo "  DLQ:       {$dlqCount}\n";
        echo "\n";
    }
}
