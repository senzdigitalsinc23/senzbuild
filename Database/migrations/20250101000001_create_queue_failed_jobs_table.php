<?php

namespace Database\Migrations;

use Database\Migration;

class CreateQueueFailedJobsTable extends Migration
{
    public function up(): void
    {
        $this->execute("
            CREATE TABLE IF NOT EXISTS queue_failed_jobs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid VARCHAR(255) NOT NULL UNIQUE,
                connection TEXT NOT NULL,
                queue TEXT NOT NULL,
                payload TEXT NOT NULL,
                exception TEXT NOT NULL,
                failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_failed_uuid (uuid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        $this->execute("DROP TABLE IF EXISTS queue_failed_jobs");
    }
}
