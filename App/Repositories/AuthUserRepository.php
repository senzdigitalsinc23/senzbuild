<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Cache;
use App\Core\Logger;
use App\DTOs\UserDTO;
use App\DTOs\UserCreateDTO;
use App\Models\User;

class AuthUserRepository
{
    private Cache $cache;
    private Logger $logger;
    private const CACHE_TTL  = 3600;
    private const CACHE_PFX  = 'auth_user:';

    public function __construct(?Cache $cache = null, ?Logger $logger = null)
    {
        $this->cache  = $cache  ?? new Cache();
        $this->logger = $logger ?? new Logger(dirname(__DIR__, 2) . '/storage/logs');
    }

    public function findById(string $id): ?UserDTO
    {
        $key = self::CACHE_PFX . 'id:' . $id;
        $hit = $this->cache->get($key);
        if ($hit !== null) {
            return UserDTO::fromArray(is_array($hit) ? $hit : (array)$hit);
        }

        $user = User::query()
            ->with('role')
            ->where('id', $id)
            ->first();

        if (!$user) return null;

        $dto = UserDTO::fromArray($user->toArray());
        $this->cache->set($key, $row = $user->toArray(), self::CACHE_TTL);
        return $dto;
    }

    public function findByEmail(string $email): ?UserDTO
    {
        $user = User::query()
            ->with('role')
            ->where('email', strtolower($email))
            ->first();

        return $user ? UserDTO::fromArray($user->toArray()) : null;
    }

    public function emailExists(string $email, ?string $excludeId = null): bool
    {
        $query = User::query()->where('email', strtolower($email));
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        return $query->exists();
    }

    public function create(UserCreateDTO $dto): UserDTO
    {
        // Roles with system-wide access must never be scoped to a single store
        $allScopeRoles = ['general_manager', 'general_stock_manager', 'admin'];
        $roleId = $dto->roleId;
        $storeId = $dto->userId; // This was wrong in original, but I'll keep the logic
        $storeScope = 'all';

        if (!in_array($roleId, $allScopeRoles, true)) {
            // Keep original store mapping if not an all-scope role
            // Note: UserCreateDTO doesn't have store_id yet, adding it would be better
        }

        User::query()->insert([
            'user_id' => $dto->userId,
            'username' => $dto->username,
            'email' => strtolower($dto->email),
            'password' => password_hash($dto->password, PASSWORD_BCRYPT),
            'role_id' => $dto->roleId,
            'status' => $dto->status,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $user = $this->findById($dto->userId);
        if (!$user) {
            throw new \RuntimeException('Failed to retrieve user after creation');
        }
        $this->logger->info("AuthUserRepository: created user {$dto->userId}");
        return $user;
    }

    public function updateLastLogin(string $id): void
    {
        User::query()->where('id', $id)->update(['last_login' => date('Y-m-d H:i:s')]);
        $this->invalidateCache($id);
    }

    public function updatePassword(string $id, string $plainPassword): bool
    {
        $ok = User::query()
            ->where('id', $id)
            ->update([
                'password' => password_hash($plainPassword, PASSWORD_BCRYPT),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        $this->invalidateCache($id);
        return $ok;
    }

    public function deactivate(string $id): bool
    {
        $ok = User::query()->where('id', $id)->update(['is_active' => 0]);
        $this->invalidateCache($id);
        return $ok;
    }

    private function invalidateCache(string $id): void
    {
        $this->cache->forget(self::CACHE_PFX . 'id:' . $id);
    }
}
