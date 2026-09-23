<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Read/Write Replica Support — route reads to replicas, writes to primary.
 *
 * Usage:
 *   // config/database.php:
 *   return [
 *       'default' => [
 *           'write' => ['driver' => 'mysql', 'host' => 'primary.db', 'dbname' => 'app'],
 *           'read'  => [
 *               ['host' => 'replica1.db', 'dbname' => 'app'],
 *               ['host' => 'replica2.db', 'dbname' => 'app'],
 *           ],
 *       ],
 *   ];
 *
 *   // In code:
 *   DB::connection('read')->query(...);  // hits replica
 *   DB::connection('write')->query(...); // hits primary
 */
class DatabaseManager
{
    protected static array $connections = [];
    protected static string $defaultConnection = 'default';
    protected static ?string $readConnection = null;

    /**
     * Register a multi-replica database configuration.
     */
    public static function register(string $name, array $config): void
    {
        self::$connections[$name] = [
            'write' => new Database($config['write'], new Logger(dirname(__DIR__) . '/storage/logs/db-write.log')),
            'read'  => array_map(
                fn($r) => new Database(array_merge($config['write'], $r), new Logger(dirname(__DIR__) . '/storage/logs/db-read.log')),
                $config['read'] ?? [$config['write']]
            ),
        ];
    }

    /**
     * Get a connection — defaults to write, switches to read for SELECT queries.
     */
    public static function connection(string $name = 'default', string $type = 'auto'): Database
    {
        $conn = self::$connections[$name] ?? null;
        if ($conn === null) {
            // Fallback to single connection
            $config = Config::get("database.{$name}", Config::get('database.default', []));
            return new Database($config, new Logger(dirname(__DIR__) . '/storage/logs/db.log'));
        }

        if ($type === 'read') {
            $readIndex = self::$readConnection ??= array_rand($conn['read']);
            return $conn['read'][$readIndex] ?? $conn['read'][0];
        }

        if ($type === 'write') {
            return $conn['write'];
        }

        // Auto: use write by default, switch to read for SELECT
        return $conn['write'];
    }

    /**
     * Execute a read query on a replica.
     */
    public static function read(string $sql, string $name = 'default', array $params = []): array
    {
        $db = self::connection($name, 'read');
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Execute a write query on the primary.
     */
    public static function write(string $sql, string $name = 'default', array $params = []): bool
    {
        $db = self::connection($name, 'write');
        $stmt = $db->getConnection()->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Get the default connection.
     */
    public static function getDefault(): Database
    {
        return self::connection(self::$defaultConnection);
    }

    /**
     * Switch the read replica index (for round-robin).
     */
    public static function rotateRead(string $name = 'default'): void
    {
        $conn = self::$connections[$name] ?? null;
        if ($conn === null || empty($conn['read'])) {
            return;
        }
        $keys = array_keys($conn['read']);
        self::$readConnection = $keys[0]; // cycle to first
    }
}
