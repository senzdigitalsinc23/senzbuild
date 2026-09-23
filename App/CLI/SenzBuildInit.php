<?php
declare(strict_types=1);

namespace App\CLI;

use App\CLI\Command;
use App\Core\Config;

/**
 * senzbuild init
 *
 * Initializes a project by:
 * - Running composer install
 * - Creating storage directories
 * - Optionally creating the database
 * - Setting up default migrations if none exist
 */
class SenzBuildInit extends Command
{
    public function handle(array $args): void
    {
        // Handle help flag
        if (in_array('--help', $args, true) || in_array('-h', $args, true)) {
            $this->info('Usage: senzbuild init [options]');
            $this->info('');
            $this->info('Initializes a project after scaffolding.');
            $this->info('');
            $this->info('Options:');
            $this->info('  --db, --create-db    Create the database from .env config');
            $this->info('  --db=<name>          Specify database name');
            $this->info('  -h, --help           Show this help message');
            $this->info('');
            $this->info('Example:');
            $this->info('  senzbuild init');
            $this->info('  senzbuild init --db');
            $this->info('  senzbuild init --db=myapp');
            return;
        }

        $createDb = in_array('--db', $args, true) || in_array('--create-db', $args, true);
        $dbName = null;

        foreach ($args as $arg) {
            if (preg_match('/^--db=(.+)$/', $arg, $matches)) {
                $dbName = $matches[1];
                $createDb = true;
            }
        }

        $this->info('Initializing project...');

        // Step 1: Composer install
        $this->info('Running composer install...');
        $composerOutput = [];
        $composerResult = 0;
        exec('composer install --no-interaction --prefer-dist 2>&1', $composerOutput, $composerResult);
        foreach ($composerOutput as $line) {
            echo "  {$line}\n";
        }

        if ($composerResult !== 0) {
            $this->error('Composer install failed. Check the output above.');
            return;
        }
        $this->success('Dependencies installed');

        // Step 2: Create storage directories
        $this->info('Creating storage directories...');
        $directories = [
            'storage/logs',
            'storage/cache',
            'storage/sessions',
            'storage/uploads',
            'storage/backups',
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
                $this->info("  Created {$dir}");
            }
        }
        $this->success('Storage directories ready');

        // Step 3: Load config and create database if requested
        if ($createDb) {
            \App\Core\ConfigCache::setBasePath(__DIR__ . '/../../../');
            Config::load(__DIR__ . '/../../../config');

            $driver = Config::get('database.driver', 'mysql');
            $host = Config::get('database.host', '127.0.0.1');
            $dbname = $dbName ?? Config::get('database.dbname', '');
            $user = Config::get('database.username', 'root');
            $pass = Config::get('database.password', '');

            if (empty($dbname)) {
                $this->warning('No database name found in config. Skipping database creation.');
            } else {
                $this->info("Creating database '{$dbname}' (driver: {$driver})...");
                $this->createDatabase($driver, $host, $dbname, $user, $pass);
            }
        }

        // Step 4: Check for migrations
        $migrationsPath = __DIR__ . '/../../../Database/Migrations';
        if (!is_dir($migrationsPath)) {
            mkdir($migrationsPath, 0755, true);
            $this->info('Created Database/Migrations directory');
        }

        $this->success('Project initialized successfully!');
        $this->info('Run: php bin/console migrate');
    }

    protected function createDatabase(string $driver, string $host, string $dbname, string $user, string $pass): void
    {
        try {
            switch (strtolower($driver)) {
                case 'mysql':
                case 'mysqli':
                    $dsn = "mysql:host={$host}";
                    $pdo = new \PDO($dsn, $user, $pass, [
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    ]);
                    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . str_replace('`', '``', $dbname) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    break;

                case 'pgsql':
                case 'postgresql':
                    $dsn = "pgsql:host={$host}";
                    $pdo = new \PDO($dsn, $user, $pass, [
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    ]);
                    $pdo->exec("SELECT 1 FROM pg_database WHERE datname = '" . addslashes($dbname) . "'");
                    $exists = $pdo->fetchColumn() !== false;
                    if (!$exists) {
                        $pdo->exec("CREATE DATABASE \"{$dbname}\"");
                    }
                    break;

                case 'sqlite':
                    $dbPath = __DIR__ . "/../../../storage/{$dbname}.sqlite";
                    $dsn = "sqlite:{$dbPath}";
                    $pdo = new \PDO($dsn, '', '');
                    $pdo = null;
                    $this->info("SQLite database created at storage/{$dbname}.sqlite");
                    return;

                case 'sqlsrv':
                case 'mssql':
                    $dsn = "sqlsrv:Server={$host};Database=master";
                    $pdo = new \PDO($dsn, $user, $pass, [
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    ]);
                    $pdo->exec("IF NOT EXISTS (SELECT * FROM sys.databases WHERE name = '{$dbname}') CREATE DATABASE [{$dbname}]");
                    break;

                default:
                    $this->warning("Unknown driver '{$driver}'. Skipping database creation.");
                    return;
            }

            $this->success("Database '{$dbname}' created or already exists");
        } catch (\PDOException $e) {
            $this->warning("Could not create database: " . $e->getMessage());
            $this->info('You can create it manually using your database client.');
        }
    }
}

