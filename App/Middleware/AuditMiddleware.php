<?php
declare(strict_types=1);


namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Utils\AuditLogger;

/**
 * Middleware that logs ALL mutating CRUD operations to the audit_logs table.
 *
 * RUNS AFTER the controller (calls $next first) so it can inspect the
 * response status code and extract created entity IDs from the response body.
 * Skips GET/HEAD/OPTIONS requests and non-2xx responses.
 *
 * Covers every section of the system:
 *   Products · Catalog · Pricing · Sales · Invoices · Payments
 *   Sales Returns · Store Credits · Purchase Orders · GRN
 *   Suppliers · Customers · HR · Payroll · Leave · Pharmacy
 *   Warehouses · Stock Requests · Store Stock · Store Requisitions
 *   Inventory · Accounting · Expenses · Tax · Banking
 *   Settings · Admin · Commissions · Approvals · RFQ
 *   Gift Cards · Storefront · Customer Portal · 2FA
 *   IP Rules · Security Alerts · Paystack · Developer Tools
 */
class AuditMiddleware
{
    /** Entity type mapping: URL path prefix → stored entity_type value.
     *
     *  Order matters: more-specific prefixes MUST come before general ones
     *  (e.g. 'hr/leave/types' before 'hr/leave').
     */
    private const ENTITY_MAP = [
        // ── Module Installation ────────────────────────────────────────────
        'modules/upload'             => 'module',
        'modules/import-git'         => 'module',
        'modules/export'             => 'module',
        'modules/export/code-list'   => 'module',
        'modules/export/code-package'=> 'module',
        'modules'                    => 'module',

        // ── Auth / Profile ──────────────────────────────────────────────────
        'profile/update'           => 'profile',
        'profile/image'            => 'profile_image',
        'auth/reset-password'      => 'password_reset',
        'auth/forgot-password'     => 'password_forgot',
        'auth/2fa/setup'           => 'two_factor',
        'auth/2fa/verify'          => 'two_factor',
        'auth/2fa/disable'         => 'two_factor',
        'register'                 => 'user_registration',

        // ── Stores ─────────────────────────────────────────────────────────
        'stores'                   => 'store',
        'store-types'              => 'store_type',

        // ── POS Terminals ──────────────────────────────────────────────────
        'pos-terminals'            => 'pos_terminal',

        // ── Catalog ────────────────────────────────────────────────────────
        'catalog/units'            => 'unit',
        'categories'               => 'category',
        'subcategories'            => 'subcategory',
        'brands'                   => 'brand',
        'units'                    => 'unit',

        // ── Products ───────────────────────────────────────────────────────
        'products'                 => 'product',
        'product-suppliers'        => 'product_supplier',

        // ── Pricing & Discounts ────────────────────────────────────────────
        'price-tiers'              => 'price_tier',
        'discounts'                => 'discount',
        'bundles'                  => 'bundle',
        'pricing/landed-cost'      => 'landed_cost',
        'pricing/products'         => 'product_pricing',
        'pricing/rules'            => 'pricing_rule',

        // ── Sales ──────────────────────────────────────────────────────────
        'sales'                    => 'sale',
        'sales-orders'             => 'sales_order',
        'invoices'                 => 'invoice',

        // ── Payments ───────────────────────────────────────────────────────
        'payments/paystack'        => 'paystack_payment',
        'payments'                 => 'payment',
        'payment-schedules'        => 'payment_schedule',

        // ── Sales Returns & Store Credits ──────────────────────────────────
        'sales-returns'            => 'sales_return',
        'store-credits'            => 'store_credit',

        // ── Suppliers (with sub-resources) ─────────────────────────────────
        'suppliers/contacts'       => 'supplier_contact',
        'suppliers/scorecards'     => 'supplier_scorecard',
        'suppliers/contracts'      => 'supplier_contract',
        'suppliers'                => 'supplier',

        // ── Customers (with sub-resources) ─────────────────────────────────
        'customers/walk-in'        => 'walkin_customer',
        'customers/credit'         => 'customer_credit',
        'customers/pricing'        => 'customer_pricing',
        'customers/purchases'      => 'customer_purchase',
        'customers/credit-transactions' => 'customer_credit_txn',
        'customers/loyalty'        => 'customer_loyalty',
        'customers'                => 'customer',
        'customer-groups'          => 'customer_group',
        'loyalty-tiers'            => 'loyalty_tier',
        'loyalty/process-run'      => 'loyalty_run',

        // ── HR: Employees ──────────────────────────────────────────────────
        'hr/employees/photo'       => 'employee_photo',
        'hr/employees/documents'   => 'employee_document',
        'hr/employees'             => 'employee',
        'hr/documents'             => 'employee_document',
        // HR: Attendance
        'hr/attendance/clock-in'   => 'attendance',
        'hr/attendance/clock-out'  => 'attendance',
        'hr/attendance/biometric'  => 'attendance',
        'hr/attendance'            => 'attendance_record',
        // HR: Schedules
        'hr/schedules'             => 'schedule',
        // HR: Biometric Devices
        'hr/biometric-devices'     => 'biometric_device',
        // HR: Leave
        'hr/leave/types'           => 'leave_type',
        'hr/leave/requests'        => 'leave_request',
        'hr/leave/balances'        => 'leave_balance',
        'hr/leave'                 => 'leave',

        // ── Payroll ────────────────────────────────────────────────────────
        'payroll/structures'       => 'payroll_structure',
        'payroll/loans'            => 'payroll_loan',
        'payroll/runs'             => 'payroll_run',
        'payroll/payslips'         => 'payslip',
        'payroll'                  => 'payroll',

        // ── Purchase Orders ────────────────────────────────────────────────
        'purchase-orders/documents'=> 'purchase_order_doc',
        'purchase-orders'          => 'purchase_order',

        // ── Purchasing ─────────────────────────────────────────────────────
        'grn'                      => 'goods_receipt_note',
        'supplier-returns'         => 'supplier_return',
        'supplier-invoices/schedules' => 'supplier_invoice_schedule',
        'supplier-invoices'        => 'supplier_invoice',
        'supplier-invoice-payments'=> 'supplier_invoice_payment',
        'payables'                 => 'payable',

        // ── Inventory ──────────────────────────────────────────────────────
        'inventory/movements'      => 'stock_movement',
        'inventory/tally'          => 'inventory_tally',
        'inventory/low-stock'      => 'low_stock_alert',
        'inventory/alerts'         => 'inventory_alert',
        'inventory/valuation'      => 'stock_valuation',

        // ── Warehouses ─────────────────────────────────────────────────────
        'warehouses/restock-requests' => 'restock_request',
        'warehouses/stock'         => 'warehouse_stock',
        'warehouses'               => 'warehouse',

        // ── Stock Requests ─────────────────────────────────────────────────
        'stock-requests'           => 'stock_request',

        // ── Store Stock & Requisitions ─────────────────────────────────────
        'store-stock'              => 'store_stock',
        'store-requisitions'       => 'store_requisition',
        'reorder-rules'            => 'reorder_rule',

        // ── Accounting ─────────────────────────────────────────────────────
        'accounts'                 => 'account',
        'transactions'             => 'transaction',
        'expenses'                 => 'expense',
        'tax-records'              => 'tax_record',

        // ── Pharmacy ───────────────────────────────────────────────────────
        'pharmacy/prescriptions'   => 'prescription',
        'pharmacy/controlled-drugs'=> 'controlled_drug',
        'pharmacy/dispensing-log'  => 'dispensing_log',
        'pharmacy/interactions'    => 'drug_interaction',

        // ── RFQ (Request for Quotation) ────────────────────────────────────
        'rfqs'                     => 'rfq',

        // ── Approvals ──────────────────────────────────────────────────────
        'approvals/submit'         => 'approval_request',
        'approvals/workflows'      => 'approval_workflow',

        // ── Commissions ────────────────────────────────────────────────────
        'commissions/rules'        => 'commission_rule',
        'commissions/records'      => 'commission_record',
        'commissions/bulk-status'  => 'commission_bulk_update',
        'commissions/generate'     => 'commission_generation',

        // ── Settings & Scheduler ───────────────────────────────────────────
        'settings/backup'          => 'system_backup',
        'settings/restore'         => 'system_restore',
        'settings'                 => 'system_setting',
        'scheduler/status'         => 'scheduler',

        // ── Admin (User Management) ────────────────────────────────────────
        'admin/users'              => 'user',

        // ── Security ──────────────────────────────────────────────────────
        'ip-rules'                 => 'ip_rule',
        'security-alerts/check'    => 'security_alert_check',

        // ── Dev / Developer Tools ─────────────────────────────────────────
        'dev/tables'               => 'developer_tool',
        'dev/truncate'             => 'developer_tool',

        // ── Storefront (public-facing) ─────────────────────────────────────
        'storefront/cart'          => 'storefront_cart',
        'storefront/checkout'      => 'storefront_checkout',
        'storefront/orders'        => 'storefront_order',
        'storefront/payments'      => 'storefront_payment',

        // ── Customer Portal ────────────────────────────────────────────────
        'portal/login'             => 'portal_login',
        'portal/logout'            => 'portal_logout',
        'portal/profile'           => 'portal_profile',

        // ── Gift Cards ────────────────────────────────────────────────────
        'gift-cards'               => 'gift_card',

        // ── Bank Reconciliation ────────────────────────────────────────────
        'bank/accounts'            => 'bank_account',
        'bank/import'              => 'bank_import',
        'bank/match'               => 'bank_match',
        'bank/auto-match'          => 'bank_auto_match',
        'bank/reconciliation'      => 'bank_reconciliation',
    ];

    /** Sub-resource actions mapped to descriptive audit action names. */
    private const SUB_ACTIONS = [
        'adjust-stock'            => 'stock_adjusted',
        'approve'                 => 'approved',
        'reject'                  => 'rejected',
        'receive'                 => 'received',
        'dispatch'                => 'dispatched',
        'cancel'                  => 'cancelled',
        'refund'                  => 'refunded',
        'void'                    => 'voided',
        'send'                    => 'sent',
        'fulfill'                 => 'fulfilled',
        'reserve'                 => 'reserved',
        'backorder'               => 'backordered',
        'invoice'                 => 'invoiced',
        'award'                   => 'awarded',
        'reconcile'               => 'reconciled',
        'blacklist'               => 'blacklisted',
        'unblacklist'             => 'unblacklisted',
        'lock'                    => 'locked',
        'unlock'                  => 'unlocked',
        'status'                  => 'status_changed',
        'process-refund'          => 'refund_processed',
        'process-run'             => 'loyalty_processed',
        'expire-run'              => 'expired',
        'use'                     => 'used',
        'topup'                   => 'topped_up',
        'redeem'                  => 'redeemed',
        'check-balance'           => 'balance_checked',
        'clock-in'                => 'clocked_in',
        'clock-out'               => 'clocked_out',
        'biometric'               => 'biometric_punch',
        'upload'                  => 'uploaded',
        'download'                => 'downloaded',
        'generate'                => 'generated',
        'regenerate'              => 'regenerated',
        'pay'                     => 'paid',
        'check'                   => 'checked',
        'setup'                   => 'setup',
        'disable'                 => 'disabled',
        'validate'                => 'validated',
        'initialize'              => 'initialized',
        'verify'                  => 'verified',
        'match'                   => 'matched',
        'auto-match'              => 'auto_matched',
        'import'                  => 'imported',
        'assign'                  => 'assigned',
        'submit'                  => 'submitted',
        'truncate'                => 'truncated',
        'auto-generate'           => 'auto_generated',
        'receive-quotation'       => 'quotation_received',
        'sanitize'                => 'sanitized',
        'restore'                 => 'restored',
        'backup'                  => 'backed_up',
        'calculate'               => 'calculated',
        'validate-margin'         => 'margin_validated',
    ];

    public function handle(Request $request, Response $response, callable $next): Response
    {
        // Run the controller first so we can inspect the response
        $result = $next($request, $response);

        // Only log mutating methods that succeeded
        $method = $request->getMethod();
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $result;
        }

        $statusCode = $result->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            return $result;
        }

        $this->logAction($request, $result);

        return $result;
    }

    private function logAction(Request $request, Response $result): void
    {
        $path   = $request->getPath();
        $method = $request->getMethod();

        // Strip API prefix (/api/v1/, /api/v2/, etc.)
        $cleanPath = preg_replace('#^/api/v\d+/#', '', $path);
        $segments  = explode('/', $cleanPath);
        $resource  = $segments[0] ?? '';

        $entityType = $this->resolveEntityType($resource, $cleanPath);
        if (!$entityType) {
            return; // Not a monitored route — skip silently
        }

        // Extract action name from method and path
        $action = $this->resolveAction($method, $segments);

        // Extract entity ID from URL segments
        $entityId = $this->extractEntityId($segments);

        // For POST (create), try to pull the ID from the response body
        if (!$entityId && $method === 'POST') {
            $entityId = $this->extractEntityIdFromResponse($result);
        }

        // Fallback: use the full path as a pseudo-id so the entry is still useful
        $entityId = $entityId ?? $path;

        // Current user from session (set by AuthMiddleware)
        $user     = Session::get('user');
        $userId   = $user['id'] ?? Session::get('user_id');
        $userName = $user['name'] ?? null;

        // Request body as the "new values" snapshot (redact sensitive fields)
        $changes = $this->sanitizeSensitiveData($request->getBodyParams());

        AuditLogger::log($entityType, $entityId, $action, $userId, $userName, $changes);
    }

    /**
     * Map the URL resource name to a stored entity_type value.
     * Tries exact match first, then prefix match for multi-segment keys.
     */
    private function resolveEntityType(string $resource, string $cleanPath): ?string
    {
        // Exact match on the full cleaned path
        if (isset(self::ENTITY_MAP[$cleanPath])) {
            return self::ENTITY_MAP[$cleanPath];
        }

        // Exact match on first segment
        if (isset(self::ENTITY_MAP[$resource])) {
            return self::ENTITY_MAP[$resource];
        }

        // Prefix match: find longest matching key
        $matched   = null;
        $matchLen  = 0;
        foreach (self::ENTITY_MAP as $prefix => $type) {
            if (str_starts_with($cleanPath, $prefix) && strlen($prefix) > $matchLen) {
                $matched  = $type;
                $matchLen = strlen($prefix);
            }
        }

        return $matched;
    }

    /**
     * Derive a human-readable action name from the HTTP method and URL segments.
     */
    private function resolveAction(string $method, array $segments): string
    {
        // Check for sub-resource actions (e.g. /products/{id}/adjust-stock)
        if (count($segments) >= 3) {
            $subAction = end($segments);
            if (isset(self::SUB_ACTIONS[$subAction])) {
                return self::SUB_ACTIONS[$subAction];
            }
        }

        // Two-segment paths like /catalog/units → POST still means 'created'
        // Standard CRUD mapping
        return match ($method) {
            'POST'   => 'created',
            'PUT'    => 'updated',
            'PATCH'  => 'patched',
            'DELETE' => 'deleted',
            default  => strtolower($method),
        };
    }

    /**
     * Extract an entity ID (UUID) from URL path segments.
     */
    private function extractEntityId(array $segments): ?string
    {
        foreach ($segments as $i => $seg) {
            if ($i === 0) {
                continue; // Skip the resource name
            }
            // Match UUIDs (36 chars with hyphens) and compact hex strings (≥ 20 chars)
            if (preg_match('/^[a-f0-9\-]{20,}$/i', $seg)) {
                return $seg;
            }
            // Also match numeric IDs for simple systems
            if (preg_match('/^\d+$/', $seg) && $seg !== end($segments)) {
                // Only return if there's a further sub-resource (e.g. /users/42/approve)
                // so we don't treat generic digits as IDs for simple routes
                continue;
            }
        }
        return null;
    }

    /**
     * Try to extract a created entity ID from the JSON response body.
     */
    private function extractEntityIdFromResponse(Response $response): ?string
    {
        $content = $response->getContent();
        if (!$content) {
            return null;
        }

        $data = json_decode($content, true);
        if (!is_array($data) || !isset($data['data'])) {
            return null;
        }

        $entity = $data['data'];
        if (!is_array($entity)) {
            return null;
        }

        // Common ID field names returned by controllers
        foreach (['id', 'uuid', 'reference_no', 'receipt_no', 'code', 'slug'] as $field) {
            if (!empty($entity[$field])) {
                return (string) $entity[$field];
            }
        }

        // Paginated responses may have 'results' instead of direct data
        if (isset($entity['results']) && is_array($entity['results']) && count($entity['results']) === 1) {
            $first = reset($entity['results']);
            foreach (['id', 'uuid', 'reference_no', 'code'] as $field) {
                if (!empty($first[$field])) {
                    return (string) $first[$field];
                }
            }
        }

        return null;
    }

    /**
     * Remove sensitive fields from the request data before logging.
     */
    private function sanitizeSensitiveData(array $data): array
    {
        $sensitive = ['password', 'password_hash', 'token', 'secret', 'api_key', 
                       'current_password', 'new_password', 'confirm_password',
                       'pin', 'otp', 'totp_secret', 'credit_card', 'cvv'];
        
        foreach ($sensitive as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[REDACTED]';
            }
        }

        return $data;
    }
}
