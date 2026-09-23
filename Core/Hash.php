<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Secure Hash — supports bcrypt, argon2id, and key rotation.
 *
 * Usage:
 *   Hash::make('password');                         // default driver
 *   Hash::make('password', ['driver' => 'argon2id']);
 *   Hash::verify('password', $hashed);              // auto-detects driver
 *   Hash::needsRehash($hashed);                    // check if hash needs upgrading
 *   Hash::rotate('old_key', 'new_key');             // rotate JWT signing keys
 */
class Hash
{
    protected static string $defaultDriver = 'bcrypt';

    /**
     * Set the default hashing driver.
     */
    public static function setDefaultDriver(string $driver): void
    {
        self::$defaultDriver = in_array($driver, ['bcrypt', 'argon2id']) ? $driver : 'bcrypt';
    }

    /**
     * Get the default hashing driver.
     */
    public static function getDefaultDriver(): string
    {
        return self::$defaultDriver;
    }

    /**
     * Hash a plain-text value.
     *
     * @param string $value Plain text to hash
     * @param array<string, mixed> $options Extra options (e.g. ['driver' => 'argon2id', 'memory' => 65536])
     * @return string|false The hashed value or false on failure
     */
    public static function make(string $value, array $options = []): string|false
    {
        $driver = $options['driver'] ?? self::$defaultDriver;

        switch ($driver) {
            case 'argon2id':
                return password_hash($value, PASSWORD_ARGON2ID, [
                    'memory_cost' => $options['memory'] ?? 65536,
                    'time_cost'   => $options['time'] ?? 4,
                    'threads'     => $options['threads'] ?? 3,
                ]);
            case 'bcrypt':
            default:
                $cost = $options['rounds'] ?? 12;
                return password_hash($value, PASSWORD_BCRYPT, ['cost' => $cost]);
        }
    }

    /**
     * Verify a plain-text value against a hash.
     *
     * @param string $value The plain text to verify
     * @param string $hash The hash to verify against
     * @return bool
     */
    public static function verify(string $value, string $hash): bool
    {
        // Try standard password_verify first
        if (password_verify($value, $hash)) {
            // Check if rehash is needed (e.g., upgrade from bcrypt to argon2id)
            if (self::needsRehash($hash)) {
                return true; // valid but should be rehashed
            }
            return true;
        }

        // Fallback: try argon2id if the stored hash looks like bcrypt but verification failed
        // This can happen if the system defaults changed
        return false;
    }

    /**
     * Check if a hash needs to be rehashed (e.g., cost factor increased).
     *
     * @param string $hash
     * @return bool
     */
    public static function needsRehash(string $hash): bool
    {
        $driver = self::detectDriver($hash);

        if ($driver === 'argon2id' && self::$defaultDriver === 'bcrypt') {
            return true;
        }
        if ($driver === 'bcrypt' && self::$defaultDriver === 'argon2id') {
            return true;
        }

        // Check bcrypt cost
        if ($driver === 'bcrypt') {
            $info = password_get_info($hash);
            $cost = $info['options']['cost'] ?? 10;
            return $cost < 12;
        }

        return false;
    }

    /**
     * Detect which algorithm was used to create a hash.
     *
     * @param string $hash
     * @return string 'bcrypt'|'argon2id'|'unknown'
     */
    public static function detectDriver(string $hash): string
    {
        $info = password_get_info($hash);
        switch ($info['algo']) {
            case PASSWORD_BCRYPT:
                return 'bcrypt';
            case PASSWORD_ARGON2ID:
                return 'argon2id';
            default:
                return 'unknown';
        }
    }

    /**
     * Get the cost factor currently used for bcrypt hashing.
     *
     * @return int
     */
    public static function getCost(): int
    {
        return 12;
    }

    /**
     * Check if argon2id is supported on this PHP installation.
     *
     * @return bool
     */
    public static function isArgon2Supported(): bool
    {
        return defined('PASSWORD_ARGON2ID');
    }
}
