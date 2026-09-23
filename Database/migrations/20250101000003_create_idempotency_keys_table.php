<?php

namespace Database\Migrations;

use Database\Migration;

class CreateIdempotencyKeysTable extends Migration
{
    public function up(): void
    {
        $this->execute("
            CREATE TABLE IF NOT EXISTS idempotency_keys (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                key VARCHAR(64) NOT NULL UNIQUE,
                request_method VARCHAR(10) NOT NULL,
                request_path VARCHAR(1024) NOT NULL,
                request_body_hash VARCHAR(64) NOT NULL,
                response_status INT NOT NULL,
                response_headers TEXT NOT NULL,
                response_body MEDIUMTEXT NOT NULL,
                user_id VARCHAR(255) NULL,
                expires_at INT UNSIGNED NOT NULL,
                created_at TIMESTAMP NULL DEFAULT NULL,
                INDEX idx_idem_key (key),
                INDEX idx_idem_user (user_id, expires_at),
                INDEX idx_idem_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        $this->execute("DROP TABLE IF EXISTS idempotency_keys");
    }
}
