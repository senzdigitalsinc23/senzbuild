<?php
declare(strict_types=1);

namespace App\Controllers\Api\v1;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Traits\JsonResponseTrait;
use App\Core\Traits\RequirePermission;
use App\Models\User;
use App\Services\UserFeatureService;
use App\Utils\AuditLogger;

class AdminController
{
    use JsonResponseTrait;
    use RequirePermission;

    private UserFeatureService $userFeatures;
    private UserRepository $userRepo;

    public function __construct()
    {
        $this->userFeatures = new UserFeatureService();
        $this->userRepo = new UserRepository();
    }

    /** GET /api/v1/admin/users */
    public function users(Request $request, Response $response): Response
    {
        if (!$this->requirePermission('users.view')) {
            return $this->denyPermission($response, 'users.view');
        }
        $users = $this->userRepo->getAllUsers();
        return $this->json($response, 200, ['success' => true, 'data' => $users]);
    }

    /** PUT /api/v1/admin/users/{id} */
    public function updateUser(Request $request, Response $response, array $params): Response
    {
        if (!$this->requirePermission('users.manage')) {
            return $this->denyPermission($response, 'users.manage');
        }
        $data = $request->getBodyParams();
        $user = $this->userRepo->findById((int)$params['id']);
        if (!$user) {
            return $this->json($response, 404, ['success' => false, 'message' => 'User not found']);
        }

        // Validate role assignment based on current user's scope
        $currentUser = Session::get('user');
        if ($currentUser && isset($data['role_id'])) {
            $targetRoleId   = $data['role_id'];
            $assignerRoleId = $currentUser['role_id'] ?? '';
            $validation = $this->validateRoleAssignment($targetRoleId, $assignerRoleId);
            if (!$validation['valid']) {
                return $this->json($response, 403, [
                    'success' => false,
                    'message' => $validation['message'],
                ]);
            }
        }

        // Enforce all-scope for roles that must have access to all stores/warehouses
        $allScopeRoles = ['general_manager', 'general_stock_manager', 'admin'];
        $roleId = $data['role_id'] ?? $user->roleId ?? null;
        if ($roleId && in_array($roleId, $allScopeRoles, true)) {
            $data['store_id']    = null;
            $data['store_scope'] = 'all';
        }

        $updateDto = UserUpdateDTO::fromArray($data);
        $this->userRepo->updateUser((int)$params['id'], $updateDto);
        return $this->json($response, 200, ['success' => true, 'data' => $this->userRepo->findById((int)$params['id'])]);
    }

    /** DELETE /api/v1/admin/users/{id} */
    public function deleteUser(Request $request, Response $response, array $params): Response
    {
        if (!$this->requirePermission('users.manage')) {
            return $this->denyPermission($response, 'users.manage');
        }
        $this->userRepo->deleteUser((int)$params['id']);
        return $this->json($response, 200, ['success' => true, 'message' => 'User deactivated']);
    }

    /** POST /api/v1/admin/users/{id}/unlock */
    public function unlockUserAccount(Request $request, Response $response, array $params): Response
    {
        $id = (int)($params['id'] ?? \App\DTOs\UnlockAccountDTO::fromArray($request->getBodyParams())->userId);
        if ($id) {
            $this->userRepo->unlockAccount($id);
        }
        return $this->json($response, 200, ['success' => true, 'message' => 'Account unlocked']);
    }

    // ── Feature Permission Endpoints ─────────────────────────────────────────

    /** GET /api/v1/admin/permissions/features — list all system features for assignment */
    public function listFeatures(Request $request, Response $response): Response
    {
        if (!$this->requirePermission('users.manage')) {
            return $this->denyPermission($response, 'users.manage');
        }
        $features = $this->userFeatures->getAllFeatures();
        return $this->json($response, 200, ['success' => true, 'data' => $features]);
    }

    /** GET /api/v1/admin/permissions/users — list all users for assignment */
    public function listUsers(Request $request, Response $response): Response
    {
        $users = $this->userFeatures->getAllUsers();
        return $this->json($response, 200, ['success' => true, 'data' => $users]);
    }

    /** GET /api/v1/admin/permissions/users/{id}/features — get user's feature access */
    public function getUserFeatures(Request $request, Response $response, array $params): Response
    {
        $user = $this->userRepo->findById((int)$params['id']);
        if (!$user) {
            return $this->json($response, 404, ['success' => false, 'message' => 'User not found']);
        }

        $features = $this->userFeatures->getUserFeatures($params['id']);
        return $this->json($response, 200, [
            'success' => true,
            'data' => [
                'user_id'   => $params['id'],
                'features'  => $features,
                'has_full_access' => $features === null,
            ],
        ]);
    }

    /** PUT /api/v1/admin/permissions/users/{id}/features — replace user's feature access */
    public function setUserFeatures(Request $request, Response $response, array $params): Response
    {
        if (!$this->requirePermission('users.manage')) {
            return $this->denyPermission($response, 'users.manage');
        }
        $user = $this->userRepo->findById((int)$params['id']);
        if (!$user) {
            return $this->json($response, 404, ['success' => false, 'message' => 'User not found']);
        }

        $dto = \App\DTOs\UserFeatureUpdateDTO::fromArray($request->getBodyParams());
        $result = $this->userFeatures->setUserFeatures($params['id'], $dto->features, $dto->grantedBy);

        if ($result['success']) {
            AuditLogger::log('user_feature', $params['id'], 'updated',
                $grantedBy, null,
                ['features' => $featureSlugs ?? 'full_access']
            );
        }

        return $this->json($response, $result['success'] ? 200 : 422, $result);
    }

    /** POST /api/v1/admin/permissions/users/{id}/features/{slug} — add a feature */
    public function addFeatureToUser(Request $request, Response $response, array $params): Response
    {
        $user = $this->userRepo->findById((int)$params['id']);
        if (!$user) {
            return $this->json($response, 404, ['success' => false, 'message' => 'User not found']);
        }

        $result = $this->userFeatures->addFeature($params['id'], $params['slug']);
        return $this->json($response, $result['success'] ? 200 : 422, $result);
    }

    /** DELETE /api/v1/admin/permissions/users/{id}/features/{slug} — remove a feature */
    public function removeFeatureFromUser(Request $request, Response $response, array $params): Response
    {
        $user = $this->userRepo->findById((int)$params['id']);
        if (!$user) {
            return $this->json($response, 404, ['success' => false, 'message' => 'User not found']);
        }

        $result = $this->userFeatures->removeFeature($params['id'], $params['slug']);
        return $this->json($response, $result['success'] ? 200 : 422, $result);
    }

    /** GET /api/v1/admin/permissions/features/{slug}/users — get users with a feature */
    public function getUsersWithFeature(Request $request, Response $response, array $params): Response
    {
        $users = $this->userFeatures->getUsersWithFeature($params['slug']);
        return $this->json($response, 200, ['success' => true, 'data' => $users]);
    }

    // ── Extra Permission Endpoints ───────────────────────────────────────────

    /** GET /api/v1/admin/permissions/users/{id}/extra-permissions — get user's extra permissions */
    public function getUserExtraPermissions(Request $request, Response $response, array $params): Response
    {
        $user = $this->userRepo->findById((int)$params['id']);
        if (!$user) {
            return $this->json($response, 404, ['success' => false, 'message' => 'User not found']);
        }

        $extra = $user->extraPermissions;

        return $this->json($response, 200, [
            'success' => true,
            'data' => [
                'user_id'           => $params['id'],
                'extra_permissions' => $extra,
            ],
        ]);
    }

    /** PUT /api/v1/admin/permissions/users/{id}/extra-permissions — replace user's extra permissions */
    public function setUserExtraPermissions(Request $request, Response $response, array $params): Response
    {
        if (!$this->requirePermission('users.manage')) {
            return $this->denyPermission($response, 'users.manage');
        }
        $user = $this->userRepo->findById((int)$params['id']);
        if (!$user) {
            return $this->json($response, 404, ['success' => false, 'message' => 'User not found']);
        }

        $data = $request->getBodyParams();
        $permissions = $data['permissions'] ?? [];

        $updateDto = new UserUpdateDTO(
            extraPermissions: $permissions
        );

        $this->userRepo->updateUser((int)$params['id'], $updateDto);

        AuditLogger::log('user_permissions', $params['id'], 'updated',
            $data['updated_by'] ?? null, null,
            ['extra_permissions' => $permissions]
        );

        return $this->json($response, 200, [
            'success'  => true,
            'message'  => 'Extra permissions updated.',
            'data' => $this->userRepo->findById((int)$params['id']),
        ]);
    }

    /**
     * Validate that the assigner is allowed to assign the given role.
     */
    private function validateRoleAssignment(string $targetRoleId, string $assignerRoleId): array
    {
        $roleScopes = [
            'general_manager'       => 'all',
            'pharmacy_manager'      => 'pharmacy',
            'supermarket_manager'   => 'supermarket',
            'hardware_manager'      => 'hardware',
            'pharmacy_sales'        => 'pharmacy',
            'supermarket_sales'     => 'supermarket',
            'hardware_sales'        => 'hardware',
            'general_stock_manager' => 'all',
            'pharmacy_stock_manager'=> 'pharmacy',
            'hardware_stock_manager'=> 'hardware',
            'developer'             => 'all',
        ];

        if ($targetRoleId === 'developer' && $assignerRoleId !== 'developer') {
            return ['valid' => false, 'message' => 'Only developers can assign the developer role'];
        }

        $assignerScope = $roleScopes[$assignerRoleId] ?? 'all';
        $targetScope   = $roleScopes[$targetRoleId] ?? 'all';

        if ($assignerScope !== 'all' && $targetScope !== 'all' && $assignerScope !== $targetScope) {
            return ['valid' => false, 'message' => "Cannot assign {$targetScope} roles from a {$assignerScope} scope"];
        }

        return ['valid' => true];
    }
}
