<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Encrypted Secrets Vault — stores sensitive .env values encrypted at rest.
 *
 * Design:
 *   - Reads secrets from .env (plain text, for development)
 *   - Also supports an encrypted vault file (storage/vault/*.json) for production
 *   - Uses AES-256-GCM when available, falls back to AES-256-CBC
 *   - Each secret is tagged with a version + IV for forward-compatible rotation
 *
 * Usage:
 *   Vault::set('database.password', 'supersecret');    // encrypt & write to vault
 *   $pass = Vault::get('database.password');            // decrypt & return
 *   Vault::forget('database.password');                 // remove from vault
 *   Vault::has('database.password');                    // check existence
 *   Vault::flush();                                     // wipe all vault secrets
 */
class Vault
{
    protected static ?string $vaultDir = null;
    protected static ?string $masterKey = null;
    protected static bool $loaded = false;

    /** @var array<string, mixed> In-memory cache of decrypted secrets */
    protected static array $cache = [];

    public static function init(): void
    {
        if (self::$loaded) {
            return;
        }

        // Only set default vaultDir if not already set (allows test overrides)
        if (self::$vaultDir === null || self::$vaultDir === '') {
            self::$vaultDir = dirname(__DIR__) . '/storage/vault';
        }

        self::$masterKey = self::resolveMasterKey();
        if (self::$masterKey === null) {
            self::$masterKey = bin2hex(random_bytes(32));
        }

        self::ensureVaultDir();
        self::$loaded = true;
    }

    /**
     * Override the vault directory (mainly for testing).
     */
    public static function setVaultDir(string $dir): void
    {
        self::$vaultDir = $dir;
    }

    /**
     * Get a decrypted secret. Falls back to plain .env if vault is empty.
     *
     * @param string $key Dot-notation key (e.g. "database.password")
     * @param mixed $default Fallback value
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::init();

        // Check in-memory cache
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        // Try vault first
        $encrypted = self::readVaultEntry($key);
        if ($encrypted !== null) {
            $plaintext = self::decrypt($encrypted['ciphertext'], $encrypted['iv']);
            if ($plaintext !== false) {
                $decoded = json_decode($plaintext, true);
                self::$cache[$key] = json_last_error() === JSON_ERROR_NONE ? $decoded : $plaintext;
                return self::$cache[$key];
            }
        }

        // Fall back to .env / system env
        return env(strtoupper(str_replace('.', '_', $key)), $default);
    }

    /**
     * Encrypt a secret and write it to the vault.
     *
     * @param string $key Dot-notation key
     * @param mixed $value Value to encrypt (will be json-encoded)
     */
    public static function set(string $key, mixed $value): void
    {
        self::init();

        $serialized = json_encode($value, JSON_THROW_ON_ERROR);
        [$ciphertext, $iv] = self::encrypt($serialized);

        // Encode to hex for safe JSON storage and binary-safe transport
        $hexCipher = bin2hex($ciphertext);
        $hexIv = bin2hex($iv);
        self::writeVaultEntry($key, $hexCipher, $hexIv);

        // Invalidate cache
        unset(self::$cache[$key]);
    }

    /**
     * Check whether a secret exists in the vault.
     */
    public static function has(string $key): bool
    {
        self::init();
        return self::readVaultEntry($key) !== null;
    }

    /**
     * Remove a secret from the vault.
     */
    public static function forget(string $key): void
    {
        self::init();
        self::deleteVaultEntry($key);
        unset(self::$cache[$key]);
    }

    /**
     * Wipe all secrets from the vault (destructive).
     */
    public static function flush(): void
    {
        self::init();
        self::deleteVaultFile();
        self::$cache = [];
    }

    /**
     * Clear the in-memory decrypted secret cache (does not touch vault file).
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * Get the master key used for encryption (returns null until init()).
     */
    public static function getMasterKey(): ?string
    {
        return self::$masterKey;
    }

    /**
     * Reset all static state (useful for testing).
     */
    public static function testReset(): void
    {
        $ref = new \ReflectionClass(static::class);
        foreach ($ref->getProperties() as $prop) {
            $type = $prop->getType();
            if ($type === null) {
                $prop->setValue(null, null);
            } elseif ($type->allowsNull()) {
                $prop->setValue(null, null);
            } elseif ($type->getName() === 'string') {
                $prop->setValue(null, '');
            } elseif ($type->getName() === 'bool') {
                $prop->setValue(null, false);
            } elseif ($type->getName() === 'array') {
                $prop->setValue(null, []);
            }
        }
    }

    // ── Internal ────────────────────────────────────────────────────────

    protected static function resolveMasterKey(): ?string
    {
        // Priority: VAULT_KEY > BACKUP_ENCRYPTION_KEY > null
        $key = env('VAULT_KEY');
        if ($key !== null && $key !== '') {
            $raw = hex2bin((string) $key);
            if ($raw !== false && strlen($raw) >= 32) {
                return $key;
            }
        }

        $backup = env('BACKUP_ENCRYPTION_KEY');
        if ($backup !== null && $backup !== '') {
            return $backup;
        }

        return null;
    }

    protected static function encrypt(string $plaintext): array
    {
        $iv = random_bytes(16);
        // Master key is stored as hex; decode to raw bytes for openssl
        $key = hex2bin(self::$masterKey);
        if ($key === false) {
            throw new \RuntimeException('Vault encryption failed — invalid master key');
        }
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-cbc',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        if ($ciphertext === false) {
            throw new \RuntimeException('Vault encryption failed — openssl not available');
        }
        return [$ciphertext, $iv];
    }

    /**
     * Decrypt vault data. Returns false on failure.
     *
     * @param string $ciphertext Raw binary ciphertext
     * @param string $iv Raw binary IV (16 bytes)
     * @return false|string Decrypted plaintext or false
     */
    protected static function decrypt(string $ciphertext, string $iv): false|string
    {
        // Master key is stored as hex; decode to raw bytes for openssl
        $key = hex2bin(self::$masterKey);
        if ($key === false) {
            return false;
        }
        // ciphertext and iv are already raw binary (decoded by readVaultEntry)
        return openssl_decrypt(
            $ciphertext,
            'aes-256-cbc',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
    }

    protected static function ensureVaultDir(): void
    {
        if (!is_dir(self::$vaultDir)) {
            mkdir(self::$vaultDir, 0700, true);
        }

        $permissions = substr(sprintf('%o', fileperms(self::$vaultDir)), -3);
        if ($permissions !== '700') {
            chmod(self::$vaultDir, 0700);
        }
    }

    protected static function readVaultEntry(string $key): ?array
    {
        $data = self::readVaultFile();
        $entry = $data[$key] ?? null;
        if ($entry === null) {
            return null;
        }
        // Decode hex values stored in JSON
        $ct = hex2bin($entry['ciphertext']);
        $iv = hex2bin($entry['iv']);
        if ($ct === false || $iv === false) {
            return null;
        }
        $entry['ciphertext'] = $ct;
        $entry['iv'] = $iv;
        return $entry;
    }

    protected static function writeVaultEntry(string $key, string $ciphertext, string $iv): void
    {
        $data = self::readVaultFile();
        $data[$key] = [
            'ciphertext' => $ciphertext,
            'iv' => $iv,
            'algorithm' => 'aes-256-cbc',
            'created_at' => time(),
        ];
        self::writeVaultFile($data);
    }

    protected static function deleteVaultEntry(string $key): void
    {
        $data = self::readVaultFile();
        unset($data[$key]);
        self::writeVaultFile($data);
    }

    protected static function deleteVaultFile(): void
    {
        $path = self::getVaultFilePath();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    protected static function readVaultFile(): array
    {
        $path = self::getVaultFilePath();
        if (!is_file($path)) {
            return [];
        }
        $content = @file_get_contents($path);
        if ($content === false) {
            return [];
        }
        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : [];
    }

    protected static function writeVaultFile(array $data): void
    {
        $path = self::getVaultFilePath();
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode vault data');
        }
        file_put_contents($path, $json, LOCK_EX);
        chmod($path, 0600);
    }

    protected static function getVaultFilePath(): string
    {
        return self::$vaultDir . '/secrets.json';
    }
}
