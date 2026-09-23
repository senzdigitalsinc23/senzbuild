<?php

/**
 * Multi-Database Configuration
 *
 * Supports: mysql, pgsql, sqlite, sqlsrv
 *
 * Usage in .env:
 *   DB_DRIVER=mysql|pgsql|sqlite|sqlsrv
 *   DB_HOST=127.0.0.1
 *   DB_NAME=your_database
 *   DB_USER=username
 *   DB_PASS=password
 *   DB_CHARSET=utf8mb4        (MySQL/PostgreSQL)
 *
 * SQLite special:
 *   DB_DRIVER=sqlite
 *   DB_NAME=database.sqlite   (file path, relative to storage/)
 *
 * PostgreSQL special:
 *   DB_DRIVER=pgsql
 *   DB_PORT=5432              (optional, defaults to 5432)
 *
 * SQL Server special:
 *   DB_DRIVER=sqlsrv
 *   DB_HOST=localhost\SQLEXPRESS  (include instance name if applicable)
 */

$driver = $_ENV['DB_DRIVER'] ?? getenv('DB_DRIVER') ?: 'mysql';

// Common configuration
$credentials = [
    'driver' => $driver,
    'host'   => $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1',
    'dbname' => $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'app_db',
    'username' => $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root',
    'password' => $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '',
];

// Driver-specific settings
switch ($driver) {
    case 'mysql':
    case 'mysqli':
        $credentials['charset'] = $_ENV['DB_CHARSET'] ?? 'utf8mb4';
        break;

    case 'pgsql':
    case 'postgresql':
        $credentials['port'] = (int) ($_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: 5432);
        $credentials['charset'] = 'utf8';
        break;

    case 'sqlite':
        // SQLite ignores host/port, dbname is the file path
        unset($credentials['host']);
        unset($credentials['port']);
        if (!str_contains($credentials['dbname'], '/')) {
            $credentials['dbname'] = __DIR__ . '/../storage/' . $credentials['dbname'];
        }
        break;

    case 'sqlsrv':
    case 'mssql':
        $credentials['charset'] = $_ENV['DB_CHARSET'] ?? 'utf8';
        // SQL Server may use Windows auth
        if (empty($credentials['username']) && empty($credentials['password'])) {
            $credentials['trusted_connection'] = 'Yes';
        }
        break;

    default:
        throw new \InvalidArgumentException("Unsupported DB_DRIVER: {$driver}. Use mysql, pgsql, sqlite, or sqlsrv.");
}

return $credentials;
