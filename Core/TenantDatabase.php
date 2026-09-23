<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Multi-tenant Database — supports separate DB per tenant via connection pooling.
 *
 * Usage:
 *   // config/database.php returns array of connections:
 *   return [
 *       'default' => ['driver' => 'mysql', 'host' => '...', 'dbname' => 'shared_db', ...],
 *       'tenant_abc' => ['driver' => 'mysql', 'host' => 'db2.example.com', 'dbname' => 'tenant_abc', ...],
 *   ];
 *
 *   // Switch tenant connection:
 *   TenantDatabase::connection('tenant_abc');
 *   $users = User::query()->get();
 *   TenantDatabase::disconnect('tenant_abc');
 */
class TenantDatabase
{
    protected static array $connections = [];
    protected static ?string $currentTenant = null;
    protected static ?string $currentSchema = null;
    protected static Database $defaultConnection;

    public static function init(Database $default): void
    {
        self::$defaultConnection = $default;
    }

    /**
     * Register a tenant database connection.
     */
    public static function register(string $tenantId, array $config): void
    {
        self::$connections[$tenantId] = self::createConnection($config);
    }

    /**
     * Switch to a tenant's database connection.
     */
    public static function connection(string $tenantId): Database
    {
        if (!isset(self::$connections[$tenantId])) {
            throw new \RuntimeException("Tenant database connection '{$tenantId}' not found");
        }
        self::$currentTenant = $tenantId;
        return self::$connections[$tenantId];
    }

    /**
     * Get the current tenant connection, or the default.
     */
    public static function getConnection(): Database
    {
        return self::$currentTenant !== null
            ? (self::$connections[self::$currentTenant] ?? self::$defaultConnection)
            : self::$defaultConnection;
    }

    /**
     * Disconnect and remove a tenant connection.
     */
    public static function disconnect(string $tenantId): void
    {
        unset(self::$connections[$tenantId]);
        if (self::$currentTenant === $tenantId) {
            self::$currentTenant = null;
        }
    }

    /**
     * Get all registered tenant connections.
     */
    public static function tenants(): array
    {
        return array_keys(self::$connections);
    }

    /**
     * Check if a tenant connection exists.
     */
    public static function hasTenant(string $tenantId): bool
    {
        return isset(self::$connections[$tenantId]);
    }

    /**
     * Get the current tenant ID.
     */
    public static function getCurrentTenant(): ?string
    {
        return self::$currentTenant;
    }

    /**
     * Reset tenant state (for CLI / tests).
     */
    public static function reset(): void
    {
        self::$currentTenant = null;
    }

    /**
     * Create a PDO-based Database instance from config.
     */
    protected static function createConnection(array $config): Database
    {
        $logger = new Logger(dirname(__DIR__) . '/storage/logs/db.log');
        return new Database($config, $logger);
    }
}

/**
 * Extension to Model for per-tenant database support.
 */
trait TenantAware
{
    /**
     * Override query() to switch to tenant DB when appropriate.
     */
    public static function query(): QueryBuilder
    {
        $tenantId = TenantMiddleware::getCurrentTenantId();
        if ($tenantId !== null && TenantDatabase::hasTenant($tenantId)) {
            TenantDatabase::connection($tenantId);
        }
        return parent::query();
    }
}
