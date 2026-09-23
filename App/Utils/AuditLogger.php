<?php
declare(strict_types=1);

namespace App\Utils;

use App\Core\Database;

/**
 * Audit Logger Utility
 * Provides immutable audit trail for all return operations
 * 
 * Features:
 * - Track user actions with timestamps and IP addresses
 * - Store before/after state changes
 * - Immutable logs (no updates or deletes)
 */
class AuditLogger
{
    /**
     * Log return creation
     * 
     * @param string $returnId Return ID
     * @param array $returnData Return data
     * @param string|null $userId User ID who created the return
     * @param string|null $userName User name
     */
    public static function logReturnCreated(
        string $returnId,
        array $returnData,
        ?string $userId = null,
        ?string $userName = null
    ): void {
        self::log(
            'sales_return',
            $returnId,
            'created',
            $userId,
            $userName,
            [
                'return_no' => $returnData['return_no'] ?? null,
                'original_sale_id' => $returnData['original_sale_id'] ?? null,
                'customer_id' => $returnData['customer_id'] ?? null,
                'total' => $returnData['total'] ?? null,
                'refund_method' => $returnData['refund_method'] ?? null,
                'reason' => $returnData['reason'] ?? null,
                'item_count' => count($returnData['items'] ?? []),
            ]
        );
    }
    
    /**
     * Log return approval
     * 
     * @param string $returnId Return ID
     * @param string $returnNo Return number
     * @param string $userId User ID who approved
     * @param string|null $userName User name
     */
    public static function logReturnApproved(
        string $returnId,
        string $returnNo,
        string $userId,
        ?string $userName = null
    ): void {
        self::log(
            'sales_return',
            $returnId,
            'approved',
            $userId,
            $userName,
            [
                'return_no' => $returnNo,
                'status_before' => 'pending',
                'status_after' => 'approved',
                'approved_at' => date('Y-m-d H:i:s'),
            ]
        );
    }
    
    /**
     * Log return rejection
     * 
     * @param string $returnId Return ID
     * @param string $returnNo Return number
     * @param string $reason Rejection reason
     * @param string|null $userId User ID who rejected
     * @param string|null $userName User name
     */
    public static function logReturnRejected(
        string $returnId,
        string $returnNo,
        string $reason,
        ?string $userId = null,
        ?string $userName = null
    ): void {
        self::log(
            'sales_return',
            $returnId,
            'rejected',
            $userId,
            $userName,
            [
                'return_no' => $returnNo,
                'status_before' => 'pending',
                'status_after' => 'rejected',
                'rejection_reason' => $reason,
            ]
        );
    }
    
    /**
     * Log refund processing
     * 
     * @param string $returnId Return ID
     * @param string $returnNo Return number
     * @param string $refundMethod Refund method
     * @param float $refundAmount Refund amount
     * @param string|null $userId User ID who processed
     * @param string|null $userName User name
     */
    public static function logRefundProcessed(
        string $returnId,
        string $returnNo,
        string $refundMethod,
        float $refundAmount,
        ?string $userId = null,
        ?string $userName = null
    ): void {
        self::log(
            'sales_return',
            $returnId,
            'refund_processed',
            $userId,
            $userName,
            [
                'return_no' => $returnNo,
                'status_before' => 'approved',
                'status_after' => 'processed',
                'refund_method' => $refundMethod,
                'refund_amount' => $refundAmount,
            ]
        );
    }
    
    /**
     * Log store credit issuance
     * 
     * @param string $creditId Credit ID
     * @param string $creditNo Credit number
     * @param string $customerId Customer ID
     * @param float $amount Credit amount
     * @param string $sourceType Source type (return, manual, etc.)
     * @param string|null $sourceId Source ID
     * @param string|null $userId User ID who issued
     * @param string|null $userName User name
     */
    public static function logCreditIssued(
        string $creditId,
        string $creditNo,
        string $customerId,
        float $amount,
        string $sourceType,
        ?string $sourceId = null,
        ?string $userId = null,
        ?string $userName = null
    ): void {
        self::log(
            'store_credit',
            $creditId,
            'issued',
            $userId,
            $userName,
            [
                'credit_no' => $creditNo,
                'customer_id' => $customerId,
                'amount' => $amount,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ]
        );
    }
    
    /**
     * Log store credit usage
     * 
     * @param string $creditId Credit ID
     * @param string $creditNo Credit number
     * @param float $amount Amount used
     * @param float $balanceBefore Balance before usage
     * @param float $balanceAfter Balance after usage
     * @param string|null $referenceId Reference ID (e.g., sale ID)
     * @param string|null $userId User ID who processed
     * @param string|null $userName User name
     */
    public static function logCreditUsed(
        string $creditId,
        string $creditNo,
        float $amount,
        float $balanceBefore,
        float $balanceAfter,
        ?string $referenceId = null,
        ?string $userId = null,
        ?string $userName = null
    ): void {
        self::log(
            'store_credit',
            $creditId,
            'used',
            $userId,
            $userName,
            [
                'credit_no' => $creditNo,
                'amount_used' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_id' => $referenceId,
            ]
        );
    }
    
    /**
     * Log store credit expiration
     * 
     * @param string $creditId Credit ID
     * @param string $creditNo Credit number
     * @param string $customerId Customer ID
     * @param float $balance Remaining balance at expiration
     * @param string $expiryDate Expiry date
     */
    public static function logCreditExpired(
        string $creditId,
        string $creditNo,
        string $customerId,
        float $balance,
        string $expiryDate
    ): void {
        self::log(
            'store_credit',
            $creditId,
            'expired',
            'system',
            'System',
            [
                'credit_no' => $creditNo,
                'customer_id' => $customerId,
                'remaining_balance' => $balance,
                'expiry_date' => $expiryDate,
                'status_before' => 'active',
                'status_after' => 'expired',
            ]
        );
    }
    
    /**
     * Core logging method
     * Creates an immutable audit log entry
     * 
     * @param string $entityType Entity type (sales_return, store_credit, etc.)
     * @param string $entityId Entity ID
     * @param string $action Action performed
     * @param string|null $userId User ID
     * @param string|null $userName User name
     * @param array $changes Changes data (before/after state)
     */
    public static function log(
        string $entityType,
        string $entityId,
        string $action,
        ?string $userId,
        ?string $userName,
        array $changes
    ): void {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Extract entity label from changes if available
            $entityLabel = $changes['return_no'] ?? $changes['credit_no'] ?? null;
            
            // Build description from changes
            $description = self::buildDescription($action, $changes);
            
            // Determine severity based on action
            $severity = self::determineSeverity($action);
            
            $sql = "
                INSERT INTO audit_logs (
                    id, user_id, user_name, module, action,
                    entity_id, entity_name, description, ip_address,
                    changes, severity
                ) VALUES (
                    :id, :user_id, :user_name, :module, :action,
                    :entity_id, :entity_name, :description, :ip_address,
                    :changes, :severity
                )
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':id' => self::uuid(),
                ':user_id' => $userId,
                ':user_name' => $userName,
                ':module' => $entityType,
                ':action' => $action,
                ':entity_id' => $entityId,
                ':entity_name' => $entityLabel,
                ':description' => $description,
                ':ip_address' => self::getClientIp(),
                ':changes' => json_encode($changes),
                ':severity' => $severity,
            ]);
            
        } catch (\Exception $e) {
            // Log error but don't fail the operation
            error_log("Audit logging failed: " . $e->getMessage());
        }
    }
    
    /**
     * Build human-readable description from action and changes
     * 
     * @param string $action Action performed
     * @param array $changes Changes data
     * @return string Description
     */
    private static function buildDescription(string $action, array $changes): string
    {
        $descriptions = [
            'created' => 'Return created',
            'approved' => 'Return approved',
            'rejected' => 'Return rejected',
            'refund_processed' => 'Refund processed',
            'issued' => 'Store credit issued',
            'used' => 'Store credit used',
            'expired' => 'Store credit expired',
        ];
        
        $baseDescription = $descriptions[$action] ?? ucfirst(str_replace('_', ' ', $action));
        
        // Add relevant details
        $details = [];
        if (isset($changes['return_no'])) {
            $details[] = $changes['return_no'];
        }
        if (isset($changes['credit_no'])) {
            $details[] = $changes['credit_no'];
        }
        if (isset($changes['refund_amount'])) {
            $details[] = 'Amount: GHS ' . number_format($changes['refund_amount'], 2);
        }
        if (isset($changes['amount'])) {
            $details[] = 'Amount: GHS ' . number_format($changes['amount'], 2);
        }
        if (isset($changes['refund_method'])) {
            $details[] = 'Method: ' . $changes['refund_method'];
        }
        
        return $baseDescription . (count($details) > 0 ? ' - ' . implode(', ', $details) : '');
    }
    
    /**
     * Determine severity level based on action
     * 
     * @param string $action Action performed
     * @return string Severity level (info, warning, critical)
     */
    private static function determineSeverity(string $action): string
    {
        $criticalActions = ['rejected', 'expired'];
        $warningActions = ['refund_processed', 'used'];
        
        if (in_array($action, $criticalActions)) {
            return 'critical';
        }
        if (in_array($action, $warningActions)) {
            return 'warning';
        }
        
        return 'info';
    }
    
    /**
     * Log a generic change to an entity
     *
     * @param string $entityType Entity type (e.g., 'User', 'Student')
     * @param string $entityId Entity ID
     * @param string $action Action performed (usually 'update')
     * @param string|null $userId User ID who made the change
     * @param array $changes Delta of changes [field => [old, new]]
     */
    public static function logChange(
        string $entityType,
        string $entityId,
        string $action,
        ?string $userId,
        array $changes
    ): void {
        $description = "Updated " . $entityType . " (ID: {$entityId})";

        // Format changes for description
        $changeDetails = [];
        foreach ($changes as $field => $values) {
            [$old, $new] = $values;
            $changeDetails[] = "{$field}: {$old} -> {$new}";
        }
        $description .= " | " . implode(', ', $changeDetails);

        self::log(
            $entityType,
            $entityId,
            $action,
            $userId,
            null, // User name (could be resolved if needed)
            $changes
        );
    }

    // ── Auth Logging ───────────────────────────────────────────────────────────

    /**
     * Log an authentication event to auth_logs.
     *
     * @param string      $event         Event type (login_success, login_failed, logout, etc.)
     * @param string|null $email         Email used for the attempt
     * @param string|null $userId        User ID (available on success)
     * @param string|null $failureReason Reason if login failed
     */
    public static function logAuth(
        string $event,
        ?string $email = null,
        ?string $userId = null,
        ?string $failureReason = null
    ): void {
        try {
            $db  = Database::getInstance()->getConnection();
            $sql = "
                INSERT INTO auth_logs (
                    id, user_id, email, event, ip_address, user_agent, failure_reason
                ) VALUES (
                    :id, :user_id, :email, :event, :ip_address, :user_agent, :failure_reason
                )
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':id'             => self::uuid(),
                ':user_id'        => $userId,
                ':email'          => $email,
                ':event'          => $event,
                ':ip_address'     => self::getClientIp(),
                ':user_agent'     => self::getUserAgent(),
                ':failure_reason' => $failureReason,
            ]);
        } catch (\Exception $e) {
            error_log('Auth logging failed: ' . $e->getMessage());
        }
    }

    /**
     * Get user agent string
     *
     * @return string|null User agent
     */
    private static function getUserAgent(): ?string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? null;
    }
    
    /**
     * Get client IP address
     * Handles proxies and load balancers
     * 
     * @return string|null Client IP address
     */
    private static function getClientIp(): ?string
    {
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',  // Proxy
            'HTTP_X_REAL_IP',        // Nginx
            'REMOTE_ADDR',           // Direct connection
        ];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (take first one)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return $ip;
            }
        }
        
        return null;
    }
    
    /**
     * Generate UUID v4
     * 
     * @return string UUID
     */
    private static function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

