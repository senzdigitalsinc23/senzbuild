<?php
declare(strict_types=1);

namespace App\Core\Traits;

use App\Core\Session;
use App\Core\Database;
use App\Utils\RoleDefinitions;

/**
 * Trait RequirePermission
 *
 * Provides a requirePermission() helper for controllers to enforce
 * both role-based and extra (per-user) permissions.
 *
 * Usage in a controller:
 *   use RequirePermission;
 *   $this->requirePermission('inventory.edit');
 */
trait RequirePermission
{
    /**
     * Check that the current user has the given permission.
     * Returns true if allowed; sends a 403 JSON response and returns false if denied.
     */
    protected function requirePermission(string $permission): bool
    {
        $sessionUser = Session::get('user');
        if (!$sessionUser) {
            return false;
        }

        $roleId = $sessionUser['role_id'] ?? $sessionUser['role'] ?? '';
        $userId = $sessionUser['id'] ?? '';

        // 1. Check role-based permissions
        if (RoleDefinitions::roleHasPermission($roleId, $permission)) {
            return true;
        }

        // 2. Check extra (per-user) permissions from database
        if ($userId && $this->userHasExtraPermission($userId, $permission)) {
            return true;
        }

        return false;
    }

    /**
     * Send a 403 Forbidden response.
     */
    protected function denyPermission($response, string $permission): object
    {
        $response->setStatusCode(403);
        $response->setHeader('Content-Type', 'application/json');
        $response->setContent(json_encode([
            'success' => false,
            'message' => "Access denied. Required permission: {$permission}",
        ]));
        return $response;
    }

    /**
     * Check if a user has a specific extra permission stored in the database.
     */
    private function userHasExtraPermission(string $userId, string $permission): bool
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT extra_permissions FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$row || empty($row['extra_permissions'])) {
                return false;
            }

            $decoded = json_decode($row['extra_permissions'], true);
            if (!is_array($decoded)) {
                return false;
            }

            return in_array($permission, $decoded, true);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
