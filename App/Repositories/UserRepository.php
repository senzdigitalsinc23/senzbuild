<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Cache;
use App\Core\Logger;
use App\DTOs\UserDTO;
use App\DTOs\UserCreateDTO;
use App\DTOs\UserUpdateDTO;
use App\Models\User;
use PDO;
use PDOException;

class UserRepository
{
    private Cache $cache;
    private Logger $logger;
    private const CACHE_TTL = 3600;

    public function __construct(?Cache $cache = null, ?Logger $logger = null)
    {
        $this->cache = $cache ?? new Cache();
        $this->logger = $logger ?? new Logger(__DIR__ . '/../../storage/logs');
    }

    public function create(UserCreateDTO $dto): UserDTO
    {
        return Database::getInstance()->transaction(function() use ($dto) {
            $user = User::query()->insert([
                'user_id' => $dto->userId,
                'username' => $dto->username,
                'email' => $dto->email,
                'password' => password_hash($dto->password, PASSWORD_BCRYPT),
                'role_id' => $dto->roleId,
                'status' => $dto->status,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // The insert method in QueryBuilder returns bool, not the model.
            // We need to fetch the inserted user.
            // Since we generate the ID in the DTO, we can find it.
            $userModel = User::query()->where('user_id', $dto->userId)->first();

            if (!$userModel) {
                throw new \RuntimeException("Failed to retrieve created user.");
            }

            return UserDTO::fromArray($userModel->toArray());
        });
    }

    public function findById(int $id): ?UserDTO
    {
        $cacheKey = "user:id:{$id}";
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached instanceof UserDTO ? $cached : UserDTO::fromArray($cached);
        }

        $user = User::query()
            ->with('role')
            ->where('id', $id)
            ->first();

        if (!$user) return null;

        $dto = UserDTO::fromArray($user->toArray());
        $this->cache->set($cacheKey, $dto->toArray(), self::CACHE_TTL);

        return $dto;
    }

    public function findByEmail(string $email): ?UserDTO
    {
        $user = User::query()
            ->with('role')
            ->where('email', $email)
            ->first();

        return $user ? UserDTO::fromArray($user->toArray()) : null;
    }

    public function getAllUsers(): array
    {
        $users = User::query()
            ->with('role')
            ->orderBy('id', 'ASC')
            ->get();

        return array_map(fn($u) => UserDTO::fromArray($u->toArray()), $users);
    }

    public function updateUser(int $id, UserUpdateDTO $dto): bool
    {
        $data = $dto->getChangedFields();
        if (empty($data)) return false;

        if (isset($data['extraPermissions'])) {
            $data['extra_permissions'] = json_encode(array_values(array_unique($data['extraPermissions'])));
            unset($data['extraPermissions']);
        }

        $success = User::query()
            ->where('id', $id)
            ->update(array_merge($data, ['updated_at' => date('Y-m-d H:i:s')]));

        if ($success) {
            $this->cache->forget("user:id:{$id}");
            if ($dto->email) {
                $this->cache->forget("user:email:" . md5($dto->email));
            }
        }

        return $success;
    }

    public function deleteUser(int $id): bool
    {
        return User::query()
            ->where('id', $id)
            ->update(['status' => 'inactive', 'updated_at' => date('Y-m-d H:i:s')]);
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $query = User::query()->where('email', $email);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        return $query->exists();
    }

    public function updatePassword(string $userId, string $hashedPassword): bool
    {
        return User::query()
            ->where('user_id', $userId)
            ->update(['password' => $hashedPassword]);
    }

    public function updateFailedAttempts(string $userId, int $attempts): bool
    {
        return User::query()
            ->where('user_id', $userId)
            ->update(['failed_login_attempts' => $attempts]);
    }

    public function resetFailedAttempts(int $id): bool
    {
        return User::query()
            ->where('id', $id)
            ->update(['failed_login_attempts' => 0, 'locked_until' => null]);
    }

    public function lockAccount(int $id, string $lockedUntil): bool
    {
        return User::query()
            ->where('id', $id)
            ->update([
                'locked_until' => $lockedUntil,
                'failed_login_attempts' => 5
            ]);
    }

    public function unlockAccount(int $id): bool
    {
        return User::query()
            ->where('id', $id)
            ->update([
                'locked_until' => null,
                'failed_login_attempts' => 0,
                'locked_by_admin' => 0
            ]);
    }
}
