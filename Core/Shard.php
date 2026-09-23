<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Database Sharding — route queries to different databases based on a shard key.
 *
 * Usage:
 *   // config/database.php:
 *   return [
 *       'shards' => [
 *           'east' => ['driver' => 'mysql', 'host' => 'db-east.internal', 'dbname' => 'app_east'],
 *           'west' => ['driver' => 'mysql', 'host' => 'db-west.internal', 'dbname' => 'app_west'],
 *       ],
 *       'shard_key' => 'region',  // column to shard by
 *   ];
 *
 *   Shard::connection('east')->table('users')->where('id', 1)->first();
 */
class Shard
{
    protected static array $shards = [];
    protected static ?string $currentShard = null;
    protected static string $shardKey = 'tenant_id';

    /**
     * Register a shard configuration.
     */
    public static function register(string $name, array $config): void
    {
        self::$shards[$name] = new Database($config, new Logger(dirname(__DIR__) . "/storage/logs/db-{$name}.log"));
    }

    /**
     * Get a connection for a specific shard.
     */
    public static function connection(string $shard): Database
    {
        if (!isset(self::$shards[$shard])) {
            throw new \RuntimeException("Shard '{$shard}' not registered");
        }
        self::$currentShard = $shard;
        return self::$shards[$shard];
    }

    /**
     * Resolve shard from a value (e.g., hash-based routing).
     */
    public static function resolve(string $value): string
    {
        $shardNames = array_keys(self::$shards);
        $index = abs(crc32($value)) % count($shardNames);
        return $shardNames[$index];
    }

    /**
     * Get the current shard name.
     */
    public static function current(): ?string
    {
        return self::$currentShard;
    }

    /**
     * Get all registered shards.
     */
    public static function listShards(): array
    {
        return array_keys(self::$shards);
    }

    /**
     * Set the default shard key column.
     */
    public static function setShardKey(string $key): void
    {
        self::$shardKey = $key;
    }

    /**
     * Reset shard state.
     */
    public static function reset(): void
    {
        self::$currentShard = null;
    }
}
