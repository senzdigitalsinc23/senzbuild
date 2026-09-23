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
        \App\Core\ConfigCache::setBasePath(dirname(__DIR__, 2));
            Config::load(dirname(__DIR__, 2) . '/config');

        $host = Config::get('database.host') ?? '127.0.0.1';
        $user = Config::get('database.username') ?? Config::get('db_user') ?? 'root';
        $pass = Config::get('database.password') ?? Config::get('db_pass') ?? '';
        $driver = Config::get('database.driver') ?? 'mysql';

        $logger = new Logger(dirname(__DIR__, 2) . '/storage/logs');

        try {
            $dsn = match (strtolower($driver)) {
                'mysql', 'mysqli' => "mysql:host={$host}",
                'pgsql', 'postgresql' => "pgsql:host={$host}",
                'sqlite' => "sqlite:" . (__DIR__ . "/../../storage/{$dbName}.sqlite"),
                'sqlsrv', 'mssql' => "sqlsrv:Server={$host}",
                default => null,
            };

            if ($dsn === null) {
                $this->error("Unsupported driver: {$driver}. Supported: mysql, pgsql, sqlite, sqlsrv");
                return;
            }

            $pdo = new \PDO($dsn, $user, $pass, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);

            match (strtolower($driver)) {
                'mysql', 'mysqli' => $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . str_replace('`', '``', $dbName) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"),
                'pgsql', 'postgresql' => $pdo->exec("SELECT 1 FROM pg_database WHERE datname = '" . addslashes($dbName) . "'"),
                'sqlite' => null, // SQLite creates file automatically
                'sqlsrv', 'mssql' => $pdo->exec("IF NOT EXISTS (SELECT * FROM sys.databases WHERE name = '{$dbName}') CREATE DATABASE [{$dbName}]"),
            };

            $this->info("Database '{$dbName}' created or already exists.");
            $logger->info("MakeDb: Created or ensured database exists: {$dbName}");
        } catch (\PDOException $e) {
            $logger->error("MakeDb failed: " . $e->getMessage());
            $this->error("Failed to create database: " . $e->getMessage());
        }
    }
}

