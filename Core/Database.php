<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use Throwable;

class Database
{
    protected PDO $connection;
    protected Logger $logger;
    protected string $driver;

    public function __construct(array $config, Logger $logger)
    {
        $this->driver = $config['driver'] ?? 'mysql';
        $this->logger = $logger;

        $connection = $this->createConnection($config);
        $this->connection = $connection;
    }

    /**
     * Create a PDO connection for any supported database driver.
     *
     * Supported drivers: mysql, pgsql, sqlite, sqlsrv
     */
    protected function createConnection(array $config): PDO
    {
        $host = $config['host'] ?? '127.0.0.1';
        $dbname = $config['dbname'] ?? '';
        $user = $config['username'] ?? 'root';
        $pass = $config['password'] ?? '';
        $charset = $config['charset'] ?? 'utf8mb4';
        $driver = $this->driver;

        try {
            switch ($driver) {
                case 'mysql':
                case 'mysqli':
                    $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
                    $options = [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ];
                    break;

                case 'pgsql':
                case 'postgresql':
                    $dsn = "pgsql:host={$host};port=5432;dbname={$dbname};options='--client_encoding=UTF8'";
                    $options = [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ];
                    break;

                case 'sqlite':
                    // For SQLite, dbname is the file path
                    $dbPath = $dbname ?: 'database.sqlite';
                    if (!str_starts_with($dbPath, '/')) {
                        $dbPath = dirname(__DIR__, 2) . '/storage/' . $dbPath;
                    }
                    $dsn = "sqlite:{$dbPath}";
                    $options = [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ];
                    // Create the file if it doesn't exist
                    $dbDir = dirname($dbPath);
                    if (!is_dir($dbDir)) {
                        mkdir($dbDir, 0755, true);
                    }
                    touch($dbPath);
                    break;

                case 'sqlsrv':
                case 'mssql':
                    $dsn = "sqlsrv:Server={$host};Database={$dbname};CharacterSet={$charset}";
                    $options = [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::SQLSRV_ENCODING => 'UTF-8',
                    ];
                    break;

                default:
                    throw new \InvalidArgumentException("Unsupported database driver: {$driver}. Supported: mysql, pgsql, sqlite, sqlsrv");
            }

            return new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            $this->logger->error("Database connection failed [{$driver}]: " . $e->getMessage());
            throw new \RuntimeException("Database connection failed: " . $e->getMessage(), 0, $e);
        }
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    public function fetch(string $sql, array $params = []): array
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fetchSingle(string $sql, array $params = []): ?array
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function query(string $sql, array $params = []): bool
    {
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute($params);
    }

    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $this->query($sql, array_values($data));
        return (int) $this->connection->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = [];
        foreach ($data as $key => $value) {
            $sets[] = "{$key} = ?";
        }
        $sql = "UPDATE {$table} SET " . implode(', ', $sets) . " WHERE {$where}";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(array_merge(array_values($data), $whereParams));
        return $stmt->rowCount();
    }

    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->connection->commit();
    }

    public function rollBack(): bool
    {
        return $this->connection->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->connection->inTransaction();
    }

    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function escape(string $value): string
    {
        return $this->connection->quote($value);
    }

    public function table(string $table, ?string $connection = null): QueryBuilder
    {
        return new QueryBuilder($this, $table);
    }

    public function getPendingQueryLogs(): array
    {
        return [];
    }

    public function enableQueryLog(): void
    {
    }

    public function disableQueryLog(): void
    {
    }

    public function resetQueryLog(): void
    {
    }
}
