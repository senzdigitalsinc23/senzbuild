<?php
declare(strict_types=1);

namespace App\CLI;

use App\Core\Config;
use PDO;

class QueueFlush extends Command
{
    protected string $name = 'queue:flush';
    protected string $description = 'Flush all pending and reserved jobs from the queue.';

    private PDO $db;

    public function handle(array $args): void
    {
        $this->db = \App\Core\Database::getInstance()->getConnection();
        $table = Config::get('queue.connections.database.table', 'queue_jobs');

        $force = in_array('--force', $args, true);
        if (!$force) {
            $this->warning("This will DELETE all pending/reserved jobs. Use --force to confirm.");
            echo "Type 'yes' to confirm: ";
            $input = trim(fgets(STDIN));
            if ($input !== 'yes') {
                $this->info("Flush cancelled.");
                return;
            }
        }

        $stmt = $this->db->prepare("DELETE FROM {$table} WHERE status IN ('pending', 'reserved')");
        $stmt->execute();
        $deleted = $stmt->rowCount();

        $this->success("Flushed {$deleted} job(s) from the queue.");
    }
}
