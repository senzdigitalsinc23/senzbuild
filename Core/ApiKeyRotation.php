<?php
declare(strict_types=1);

namespace App\Core;

/**
 * API Key Rotation Service — manage and rotate API keys securely.
 *
 * Usage:
 *   $rotator = new ApiKeyRotation();
 *   $newKey = $rotator->rotate('my-api-key', 'New key description');
 *   $rotator->revoke('my-api-key');
 */
class ApiKeyRotation
{
    protected Logger $logger;
    protected int $gracePeriod; // seconds to allow old keys during rotation

    public function __construct(?Logger $logger = null)
    {
        $this->logger = $logger ?? new Logger(dirname(__DIR__) . '/storage/logs/api_key_rotation.log');
        try {
            $this->gracePeriod = (int)Config::get('api_key.rotation_grace_period', 3600);
        } catch (\Exception $e) {
            $this->gracePeriod = 3600;
        }
    }

    /**
     * Generate a new API key.
     *
     * @param string $name Human-readable name for the key
     * @param array $permissions Allowed permissions/scopes
     * @return array{key: string, key_hash: string, name: string, permissions: array, created_at: int}
     */
    public function generate(string $name, array $permissions = ['read']): array
    {
        $key = $this->generateKey();
        $hash = password_hash($key, PASSWORD_ARGON2ID);
        $record = [
            'key'         => $key,
            'key_hash'    => $hash,
            'name'        => $name,
            'permissions' => $permissions,
            'active'      => true,
            'created_at'  => time(),
            'expires_at'  => null,
            'last_used'   => null,
        ];

        $this->store($record);
        $this->logger->info("API key generated: {$name} (ID: {$record['key']})");

        return $record;
    }

    /**
     * Rotate an existing API key — old key remains valid for grace period.
     *
     * @param string $oldKeyName The name/identifier of the old key
     * @param string $newName New key name
     * @param array $permissions New permissions
     * @return array New key record
     */
    public function rotate(string $oldKeyName, string $newName, array $permissions = []): array
    {
        $oldKey = $this->findByName($oldKeyName);
        if ($oldKey === null) {
            throw new \InvalidArgumentException("API key '{$oldKeyName}' not found");
        }

        // Generate new key
        $newKey = $this->generate($newName, $permissions ?: $oldKey['permissions']);

        // Mark old key for rotation (will expire after grace period)
        $newKey['rotated_from'] = $oldKeyName;
        $newKey['rotation_at'] = time();

        // Schedule deactivation of old key
        $this->scheduleDeactivation($oldKeyName, time() + $this->gracePeriod);

        $this->logger->info("API key rotated: {$oldKeyName} -> {$newName}");

        return $newKey;
    }

    /**
     * Revoke an API key immediately.
     *
     * @param string $keyName Key name or identifier
     * @return bool
     */
    public function revoke(string $keyName): bool
    {
        $key = $this->findByName($keyName);
        if ($key === null) {
            return false;
        }

        $key['active'] = false;
        $key['revoked_at'] = time();
        $this->store($key);

        $this->logger->warning("API key revoked: {$keyName}");
        return true;
    }

    /**
     * Verify an API key is valid and active.
     *
     * @param string $key The raw API key
     * @return array|null Key data if valid, null if invalid/expired
     */
    public function verify(string $key): ?array
    {
        $hash = password_hash($key, PASSWORD_ARGON2ID);

        // Check active keys
        $active = $this->getActiveKeys();
        foreach ($active as $record) {
            if (password_verify($key, $record['key_hash'])) {
                // Check if rotated and grace period expired
                if (!empty($record['rotated_from']) && $record['rotation_at'] !== null) {
                    $graceEnd = $record['rotation_at'] + $this->gracePeriod;
                    if (time() > $graceEnd) {
                        // Grace period expired, this key is dead
                        continue;
                    }
                }

                // Update last used
                $record['last_used'] = time();
                $this->store($record);

                return $record;
            }
        }

        return null;
    }

    /**
     * Schedule a key for deactivation after grace period.
     */
    public function scheduleDeactivation(string $keyName, int $atTime): void
    {
        $scheduleKey = "key_rotation:{$keyName}";
        $cache = new Cache();
        $cache->set($scheduleKey, ['key_name' => $keyName, 'deactivate_at' => $atTime], $atTime - time() + 60);
    }

    /**
     * Get all active API keys (without raw keys).
     */
    public function listKeys(): array
    {
        $keys = $this->getActiveKeys();
        return array_map(function ($k) {
            return [
                'name'        => $k['name'],
                'key_preview' => substr($k['key'], 0, 8) . '...',
                'permissions' => $k['permissions'],
                'created_at'  => $k['created_at'],
                'last_used'   => $k['last_used'],
                'active'      => $k['active'],
                'rotated_from'=> $k['rotated_from'] ?? null,
            ];
        }, $keys);
    }

    /**
     * Clean up expired rotation schedules.
     */
    public function cleanup(): void
    {
        $cache = new Cache();
        foreach ($this->getActiveKeys() as $key) {
            if (!$key['active']) {
                $scheduleKey = "key_rotation:{$key['name']}";
                $cache->forget($scheduleKey);
            }
        }
    }

    // ── Internal ─────────────────────────────────────────────────────────

    protected function generateKey(): string
    {
        return 'sk_' . bin2hex(random_bytes(32));
    }

    protected function findByName(string $name): ?array
    {
        $keys = $this->getActiveKeys();
        foreach ($keys as $key) {
            if ($key['name'] === $name) {
                return $key;
            }
        }
        return null;
    }

    protected function getActiveKeys(): array
    {
        // In production, this would read from database
        // For now, use cache as temporary storage
        $cache = new Cache();
        $keys = $cache->get('api_keys', []);
        return is_array($keys) ? $keys : [];
    }

    protected function store(array $record): void
    {
        $cache = new Cache();
        $keys = $cache->get('api_keys', []);
        if (!is_array($keys)) {
            $keys = [];
        }

        // Index by name
        $keys[$record['name']] = $record;
        $cache->set('api_keys', $keys, 86400);
    }
}
