<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Secret Key Manager — handles JWT key rotation and storage.
 *
 * Usage:
 *   KeyManager::store('jwt', 'current_key');         // store active key
 *   KeyManager::rotate('jwt', 'new_key');             // rotate: old becomes active, new becomes previous
 *   KeyManager::getActive('jwt');                     // get current active key
 *   KeyManager::getPrevious('jwt');                   // get previous key (for signature verification)
 *   KeyManager::getKeys('jwt');                       // get all keys [active, previous]
 *   KeyManager::hasActive('jwt');                     // check if active key exists
 */
class KeyManager
{
    protected static array $keys = [];

    /**
     * Maximum number of previous keys to retain for verification.
     */
    public const MAX_PREVIOUS_KEYS = 3;

    /**
     * Store or update a key for a given name.
     *
     * @param string $name Key name (e.g. 'jwt', 'api')
     * @param string $key The secret key value
     * @return void
     */
    public static function store(string $name, string $key): void
    {
        self::$keys[$name] = [
            'active' => $key,
            'previous' => [],
            'rotated_at' => time(),
        ];
    }

    /**
     * Rotate a key — the current active key becomes previous, and the new key becomes active.
     *
     * @param string $name Key name
     * @param string $newKey The new active key
     * @return void
     */
    public static function rotate(string $name, string $newKey): void
    {
        if (!isset(self::$keys[$name])) {
            self::store($name, $newKey);
            return;
        }

        $data = self::$keys[$name];

        // Current active becomes previous
        if (!empty($data['active'])) {
            array_unshift($data['previous'], $data['active']);
        }

        // Keep only the last MAX_PREVIOUS_KEYS
        $data['previous'] = array_slice($data['previous'], 0, self::MAX_PREVIOUS_KEYS);
        $data['active'] = $newKey;
        $data['rotated_at'] = time();

        self::$keys[$name] = $data;
    }

    /**
     * Get the currently active key.
     *
     * @param string $name
     * @return string|null
     */
    public static function getActive(string $name): ?string
    {
        return self::$keys[$name]['active'] ?? null;
    }

    /**
     * Get the previous (rotated) key for signature verification.
     *
     * @param string $name
     * @return string|null
     */
    public static function getPrevious(string $name): ?string
    {
        return self::$keys[$name]['previous'][0] ?? null;
    }

    /**
     * Get all keys for a name: ['active' => ..., 'previous' => [...]].
     *
     * @param string $name
     * @return array|null
     */
    public static function getKeys(string $name): ?array
    {
        return self::$keys[$name] ?? null;
    }

    /**
     * Check if a key name has an active key.
     *
     * @param string $name
     * @return bool
     */
    public static function hasActive(string $name): bool
    {
        return !empty(self::$keys[$name]['active']);
    }

    /**
     * Get the rotation timestamp.
     *
     * @param string $name
     * @return int|null
     */
    public static function getRotatedAt(string $name): ?int
    {
        return self::$keys[$name]['rotated_at'] ?? null;
    }

    /**
     * Clear all keys.
     */
    public static function flush(): void
    {
        self::$keys = [];
    }

    /**
     * Generate a cryptographically secure random key.
     *
     * @param int $length Number of bytes (default 32)
     * @return string Hex-encoded key
     */
    public static function generate(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }
}
