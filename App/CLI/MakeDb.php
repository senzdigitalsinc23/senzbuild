<?php
declare(strict_types=1);
namespace App\CLI;

use App\Core\Config;
use App\Core\Logger;

class MakeDb extends Command
{
    public function handle(array $args): void
    {
        $dbName = $args[0] ?? null;
        if (!$dbName) {
            $this->error("Please provide a database name. Usage: php bin/console make:db <dbname>");
            return;
        }

        // Load config so we can read DB host/credentials
        Config::load(dirname(__DIR__, 2) . '/config');

        $host = Config::get('database.host') ?? '127.0.0.1';
        $user = Config::get('database.username') ?? Config::get('db_user') ?? 'root';
        $pass = Config::get('database.password') ?? Config::get('db_pass') ?? '';
        $driver = Config::get('database.driver') ?? 'mysql';

        $logger = new Logger(dirname(__DIR__, 2) . '/storage/logs');

        try {
            if (strtolower($driver) !== 'mysql' && strtolower($driver) !== 'mysqli') {
                $this->error("Unsupported driver: {$driver}. This command currently supports MySQL.");
                return;
            }

            $dsn = "mysql:host={$host}";
            $pdo = new \PDO($dsn, $user, $pass, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);

            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . str_replace('`', '``', $dbName) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;");

            $this->info("Database '{$dbName}' created or already exists.");
            $logger->info("MakeDb: Created or ensured database exists: {$dbName}");
        } catch (\PDOException $e) {
            $logger->error("MakeDb failed: " . $e->getMessage());
            $this->error("Failed to create database: " . $e->getMessage());
        }
    }
}
