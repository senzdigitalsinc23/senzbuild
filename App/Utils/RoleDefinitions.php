<?php
declare(strict_types=1);

namespace App\Utils;

/**
 * Centralised role → permission mapping (mirrors lib/roles.ts on the frontend).
 */
class RoleDefinitions
{
    private const ALL_PERMISSIONS = [
        'dashboard.view',
        'pos.supermarket', 'pos.pharmacy', 'pos.hardware',
        'inventory.view', 'inventory.view.supermarket', 'inventory.view.pharmacy', 'inventory.view.hardware',
        'inventory.add', 'inventory.edit', 'inventory.adjust_stock',
        'inventory.suppliers', 'inventory.purchase_orders', 'inventory.low_stock',
        'hr.view', 'hr.manage', 'hr.payroll', 'hr.attendance',
        'accounting.view', 'accounting.manage',
        'reports.sales', 'reports.sales.supermarket', 'reports.sales.pharmacy', 'reports.sales.hardware',
        'reports.inventory', 'reports.financial', 'reports.hr',
        'settings.view', 'settings.manage',
        'users.view', 'users.manage',
        'pharmacy.verify',
    ];

    private const ROLE_PERMISSIONS = [
        'general_manager'       => self::ALL_PERMISSIONS,
        'developer'             => self::ALL_PERMISSIONS,
        'pharmacy_manager'      => [
            'dashboard.view', 'pos.pharmacy',
            'inventory.view', 'inventory.view.pharmacy', 'inventory.add', 'inventory.edit',
            'inventory.adjust_stock', 'inventory.low_stock', 'inventory.suppliers', 'inventory.purchase_orders',
            'hr.view', 'hr.manage',
            'reports.sales', 'reports.sales.pharmacy', 'reports.inventory',
            'settings.view', 'users.view',
            'pharmacy.verify',
        ],
        'supermarket_manager'   => [
            'dashboard.view', 'pos.supermarket',
            'inventory.view', 'inventory.view.supermarket', 'inventory.add', 'inventory.edit',
            'inventory.adjust_stock', 'inventory.low_stock', 'inventory.suppliers', 'inventory.purchase_orders',
            'hr.view', 'hr.manage',
            'reports.sales', 'reports.sales.supermarket', 'reports.inventory',
            'settings.view', 'users.view',
        ],
        'hardware_manager'      => [
            'dashboard.view', 'pos.hardware',
            'inventory.view', 'inventory.view.hardware', 'inventory.add', 'inventory.edit',
            'inventory.adjust_stock', 'inventory.low_stock', 'inventory.suppliers', 'inventory.purchase_orders',
            'hr.view', 'hr.manage',
            'reports.sales', 'reports.sales.hardware', 'reports.inventory',
            'settings.view', 'users.view',
        ],
        'pharmacy_sales'        => [
            'dashboard.view', 'pos.pharmacy',
            'inventory.view', 'inventory.view.pharmacy', 'inventory.low_stock',
            'reports.sales', 'reports.sales.pharmacy',
            'pharmacy.verify',
        ],
        'supermarket_sales'     => [
            'dashboard.view', 'pos.supermarket',
            'inventory.view', 'inventory.view.supermarket', 'inventory.low_stock',
            'reports.sales', 'reports.sales.supermarket',
        ],
        'hardware_sales'        => [
            'dashboard.view', 'pos.hardware',
            'inventory.view', 'inventory.view.hardware', 'inventory.low_stock',
            'reports.sales', 'reports.sales.hardware',
        ],
        'general_stock_manager' => [
            'dashboard.view',
            'inventory.view', 'inventory.add', 'inventory.edit', 'inventory.adjust_stock',
            'inventory.suppliers', 'inventory.purchase_orders', 'inventory.low_stock',
            'reports.inventory',
            'settings.view',
        ],
        'pharmacy_stock_manager'=> [
            'dashboard.view',
            'inventory.view', 'inventory.view.pharmacy', 'inventory.add', 'inventory.edit',
            'inventory.adjust_stock', 'inventory.suppliers', 'inventory.purchase_orders', 'inventory.low_stock',
            'reports.inventory',
            'settings.view',
        ],
        'hardware_stock_manager'=> [
            'dashboard.view',
            'inventory.view', 'inventory.view.hardware', 'inventory.add', 'inventory.edit',
            'inventory.adjust_stock', 'inventory.suppliers', 'inventory.purchase_orders', 'inventory.low_stock',
            'reports.inventory',
            'settings.view',
        ],
    ];

    /**
     * Check if a role has a specific permission.
     */
    public static function roleHasPermission(string $roleId, string $permission): bool
    {
        $perms = self::ROLE_PERMISSIONS[$roleId] ?? [];
        return in_array($permission, $perms, true);
    }

    /**
     * Get all permissions for a role.
     */
    public static function getRolePermissions(string $roleId): array
    {
        return self::ROLE_PERMISSIONS[$roleId] ?? [];
    }
}
