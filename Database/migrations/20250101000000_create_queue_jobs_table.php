<?php

namespace Database\Migrations;

use Database\Migration;

class CreateQueueJobsTable extends Migration
{
    public function up(): void
    {
        $this->execute("
            CREATE TABLE IF NOT EXISTS queue_jobs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                job_class VARCHAR(255) NOT NULL,
                queue VARCHAR(191) NOT NULL DEFAULT 'default',
                payload TEXT NOT NULL,
                attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                max_attempts INT UNSIGNED NOT NULL DEFAULT 3,
                reserved_at INT UNSIGNED NULL,
                available_at INT UNSIGNED NOT NULL,
                failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                error_message TEXT NULL,
                status ENUM('pending', 'reserved', 'completed', 'failed', 'released') NOT NULL DEFAULT 'pending',
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                INDEX idx_queue_status (queue, status),
                INDEX idx_available_at (available_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        $this->execute("DROP TABLE IF EXISTS queue_jobs");
    }
}
