<?php
declare(strict_types=1);

namespace Database\ORM\Traits;

/**
 * Encryptable trait — transparently encrypts/decrypts specified model fields.
 *
 * Usage in a model:
 *   class Patient extends Model
 *   {
 *       use \Database\ORM\Traits\Encryptable;
 *       protected array $encryptable = ['ssn', 'phone', 'email'];
 *   }
 *
 * Fields listed in $encryptable are:
 *   - Encrypted BEFORE INSERT / UPDATE
 *   - Decrypted AFTER SELECT (on __get / find / query results)
 *
 * Requires ENCRYPTION_KEY in .env (32-byte hex string).
 *   Generate with: php -r "echo bin2hex(random_bytes(32));"
 */
trait Encryptable
{
    /**
     * Fields to encrypt/decrypt automatically.
     * Override in child model: protected array $encryptable = ['ssn', 'phone'];
     */
    protected array $encryptable = [];

    /**
     * Decrypt attributes after loading from DB.
     */
    public function syncOriginal(): void
    {
        foreach ($this->encryptable as $field) {
            if (isset($this->attributes[$field]) && is_string($this->attributes[$field])) {
                $decrypted = self::decrypt($this->attributes[$field]);
                if ($decrypted !== false) {
                    $this->attributes[$field] = $decrypted;
                }
            }
        }
    }

    /**
     * Encrypt attributes before persisting to DB.
     */
    public function preSave(): void
    {
        foreach ($this->encryptable as $field) {
            if (isset($this->attributes[$field]) && is_string($this->attributes[$field])) {
                $encrypted = self::encrypt($this->attributes[$field]);
                if ($encrypted !== false) {
                    $this->attributes[$field] = $encrypted;
                }
            }
        }
    }

    // ── Encryption helpers ───────────────────────────────────────────────────

    /**
     * Encrypt a plain-text value.
     *
     * @return string|false Base64-encoded ciphertext, or false on failure
     */
    public static function encrypt(string $value): string|false
    {
        $key = self::getKey();
        if ($key === false) {
            return $value; // Encryption key not configured — return plaintext
        }

        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv);
        if ($ciphertext === false) {
            return false;
        }

        // Prepend IV to ciphertext: base64(iv + ciphertext)
        return base64_encode($iv . $ciphertext);
    }

    /**
     * Decrypt a base64-encoded ciphertext.
     *
     * @return string|false Decrypted value, or false on failure
     */
    public static function decrypt(string $value): string|false
    {
        $key = self::getKey();
        if ($key === false) {
            return $value;
        }

        $data = base64_decode($value, true);
        if ($data === false || strlen($data) < 32) {
            return false;
        }

        $iv   = substr($data, 0, 16);
        $ct   = substr($data, 16);
        $plain = openssl_decrypt($ct, 'AES-256-CBC', $key, 0, $iv);

        return $plain === false ? false : $plain;
    }

    /**
     * Get the encryption key from environment.
     *
     * @return string|false 32-byte raw key, or false if not configured
     */
    protected static function getKey(): string|false
    {
        $hex = $_ENV['ENCRYPTION_KEY'] ?? getenv('ENCRYPTION_KEY');
        if (empty($hex)) {
            return false;
        }
        $raw = hex2bin($hex);
        return $raw !== false && strlen($raw) === 32 ? $raw : false;
    }

    /**
     * List fields configured for encryption on this model.
     */
    public function getEncryptableFields(): array
    {
        return $this->encryptable;
    }
}
