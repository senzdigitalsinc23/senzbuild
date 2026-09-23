<?php
declare(strict_types=1);

namespace Services;

use App\Core\Database;
use App\Core\Logger;
use App\Core\LoggerFactory;
use PDO;

class AuditService
{
    private PDO $db;
    private Logger $logger;

    public function __construct(?PDO $db = null, ?Logger $logger = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->logger = $logger ?? LoggerFactory::getInstance();
    }

    public function log(
        string $action,
        string $entityType,
        string $entityId,
        array $oldValues = [],
        array $newValues = [],
        ?string $userId = null,
        ?string $description = null,
        ?string $ipAddress = null
    ): void {
        $ipAddress ??= $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $userId ??= $_SERVER['LOGGED_USER_ID'] ?? 'system';
        $description ??= "{$action} on {$entityType}#{$entityId}";

        $changes = $this->computeChanges($oldValues, $newValues);

        $stmt = $this->db->prepare(
            "INSERT INTO audit_logs 
             (user_id, action, entity_type, entity_id, old_values, new_values, changes, description, ip_address, created_at)
             VALUES 
             (:user_id, :action, :entity_type, :entity_id, :old_values, :new_values, :changes, :description, :ip_address, NOW())"
        );

        $stmt->execute([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => json_encode($oldValues),
            'new_values' => json_encode($newValues),
            'changes' => json_encode($changes),
            'description' => $description,
            'ip_address' => $ipAddress,
        ]);

        $this->logger->info("Audit: {$description}", [
            'action' => $action,
            'entity' => "{$entityType}#{$entityId}",
            'user' => $userId,
        ]);
    }

    public function logCreate(string $entityType, string $entityId, array $values, ?string $userId = null): void
    {
        $this->log('CREATE', $entityType, $entityId, [], $values, $userId);
    }

    public function logUpdate(string $entityType, string $entityId, array $oldValues, array $newValues, ?string $userId = null): void
    {
        $this->log('UPDATE', $entityType, $entityId, $oldValues, $newValues, $userId);
    }

    public function logDelete(string $entityType, string $entityId, array $oldValues, ?string $userId = null): void
    {
        $this->log('DELETE', $entityType, $entityId, $oldValues, [], $userId);
    }

    public function logAuth(string $action, string $userId, ?string $description = null): void
    {
        $this->log($action, 'user', $userId, [], [], $userId, $description ?? "User {$action}");
    }

    public function getHistory(string $entityType, string $entityId, int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM audit_logs 
             WHERE entity_type = :entity_type AND entity_id = :entity_id 
             ORDER BY created_at DESC 
             LIMIT :limit"
        );
        $stmt->bindValue(':entity_type', $entityType, PDO::PARAM_STR);
        $stmt->bindValue(':entity_id', $entityId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByUser(string $userId, int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM audit_logs 
             WHERE user_id = :user_id 
             ORDER BY created_at DESC 
             LIMIT :limit"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByAction(string $action, int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM audit_logs 
             WHERE action = :action 
             ORDER BY created_at DESC 
             LIMIT :limit"
        );
        $stmt->bindValue(':action', $action, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function computeChanges(array $old, array $new): array
    {
        $changes = [];
        foreach ($new as $key => $value) {
            if (!array_key_exists($key, $old)) {
                $changes[$key] = ['old' => null, 'new' => $value];
            } elseif ($old[$key] !== $value) {
                $changes[$key] = ['old' => $old[$key], 'new' => $value];
            }
        }
        return $changes;
    }
}