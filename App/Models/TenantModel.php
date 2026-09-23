<?php
declare(strict_types=1);

namespace App\Models;

use Database\ORM\Model;

/**
 * Tenant-aware base model.
 *
 * Extend this instead of Model to auto-scope all queries to the current tenant.
 *
 * Usage:
 *   class Product extends TenantModel
 *   {
 *       protected static string $table = 'products';
 *   }
 *
 * Disable scoping for a specific query:
 *   Product::query()->withoutTenantScope()->where('id', 1)->first();
 */
abstract class TenantModel extends Model
{
    protected static bool $tenantScoped = true;
    protected static bool $skipScope    = false;

    /**
     * Override query() to auto-scope to the current tenant.
     */
    public static function query(): \Database\ORM\QueryBuilder
    {
        $qb = parent::query();
        if (static::$tenantScoped && !static::$skipScope) {
            $tenantId = \App\Middleware\TenantMiddleware::getCurrentTenantId();
            if ($tenantId !== null) {
                $qb->where('tenant_id', $tenantId);
            }
        }
        return $qb;
    }

    /**
     * Temporarily disable tenant scoping for a single query chain.
     */
    public static function withoutTenantScope(): self
    {
        static::$skipScope = true;
        return new static();
    }

    /**
     * Re-enable tenant scoping.
     */
    public static function withTenantScope(): void
    {
        static::$skipScope = false;
    }

    /**
     * Get the tenant ID for this model instance.
     */
    public function getTenantId(): ?string
    {
        return $this->attributes['tenant_id'] ?? null;
    }
}
