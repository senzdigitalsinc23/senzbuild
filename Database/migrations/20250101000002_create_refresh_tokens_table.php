<?php

namespace Database\Migrations;

use Database\Migration;

class CreateRefreshTokensTable extends Migration
{
    public function up(): void
    {
        $this->execute("
            CREATE TABLE IF NOT EXISTS refresh_tokens (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(255) NOT NULL,
                token_hash VARCHAR(64) NOT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                expires_at INT UNSIGNED NOT NULL,
                revoked_at INT UNSIGNED NULL,
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                INDEX idx_refresh_user_id (user_id),
                INDEX idx_refresh_token_hash (token_hash),
                INDEX idx_refresh_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        $this->execute("DROP TABLE IF EXISTS refresh_tokens");
    }
}
