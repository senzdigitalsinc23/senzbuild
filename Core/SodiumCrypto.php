<?php
declare(strict_types=1);

namespace App\Core;

/**
 * libsodium Encryption — secure encryption at rest using Sodium.
 *
 * Replaces OpenSSL AES-256-CBC with libsodium's sealed boxes and secret boxes.
 * Requires PHP 7.2+ with ext-sodium (or paragonie/sodium_compat polyfill).
 *
 * Usage:
 *   $encrypted = SodiumCrypto::encrypt('secret data', $key);
 *   $decrypted = SodiumCrypto::decrypt($encrypted, $key);
 *   $sealed = SodiumCrypto::seal('data', $recipientPublicKey);
 *   $unsealed = SodiumCrypto::unseal($sealed, $keyPair);
 */
class SodiumCrypto
{
    /**
     * Encrypt data using a shared key (secret box).
     *
     * @param string $plaintext
     * @param string $key 32-byte key (or hex-encoded)
     * @return string Encrypted data (hex-encoded ciphertext + nonce)
     */
    public static function encrypt(string $plaintext, string $key): string
    {
        self::ensureSodium();
        $key = self::normalizeKey($key);
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);
        return base64_encode($nonce . $ciphertext);
    }

    /**
     * Decrypt data encrypted with encrypt().
     *
     * @param string $encrypted Base64-encoded ciphertext+nonce
     * @param string $key 32-byte key
     * @return false|string Decrypted plaintext or false on failure
     */
    public static function decrypt(string $encrypted, string $key): false|string
    {
        self::ensureSodium();
        $key = self::normalizeKey($key);
        $data = base64_decode($encrypted, true);
        if ($data === false) {
            return false;
        }
        if (strlen($data) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            return false;
        }
        $nonce = mb_substr($data, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
        $ciphertext = mb_substr($data, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');
        return sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
    }

    /**
     * Seal data for a recipient using their public key.
     * Only the recipient's private key can unseal.
     *
     * @param string $plaintext
     * @param string $recipientPublicKey 32-byte public key (or hex-encoded)
     * @return string Sealed ciphertext (base64)
     */
    public static function seal(string $plaintext, string $recipientPublicKey): string
    {
        self::ensureSodium();
        $pubKey = self::normalizeKey($recipientPublicKey);
        return base64_encode(sodium_crypto_box_seal($plaintext, $pubKey));
    }

    /**
     * Unseal data that was sealed for this keypair.
     *
     * @param string $sealed Base64-encoded sealed ciphertext
     * @param string $keyPair Hex-encoded key pair (64 bytes: 32 public + 32 private)
     * @return false|string Decrypted plaintext or false
     */
    public static function unseal(string $sealed, string $keyPair): false|string
    {
        self::ensureSodium();
        $pair = hex2bin($keyPair);
        if ($pair === false || strlen($pair) !== 64) {
            return false;
        }
        $data = base64_decode($sealed, true);
        if ($data === false) {
            return false;
        }
        return sodium_crypto_box_seal_open($data, $pair);
    }

    /**
     * Generate a new key pair for sealed box operations.
     *
     * @return array{public_key: string, secret_key: string, key_pair_hex: string}
     */
    public static function generateKeyPair(): array
    {
        self::ensureSodium();
        [$publicKey, $secretKey] = sodium_crypto_box_keypair();
        return [
            'public_key'    => $publicKey,
            'secret_key'    => $secretKey,
            'key_pair_hex'  => bin2hex($publicKey . $secretKey),
        ];
    }

    /**
     * Generate a random secret key for secretbox operations.
     *
     * @return string 32-byte key (raw)
     */
    public static function generateSecretKey(): string
    {
        self::ensureSodium();
        return sodium_crypto_secretbox_keygen();
    }

    /**
     * Compute a cryptographic hash (SHA-512).
     */
    public static function hash(string $data): string
    {
        self::ensureSodium();
        return sodium_crypto_generichash($data, '', 32);
    }

    /**
     * Constant-time string comparison.
     */
    public static function compare(string $a, string $b): bool
    {
        self::ensureSodium();
        return sodium_compare($a, $b) === 0;
    }

    /**
     * Derive a key from a password using PBKDF2.
     *
     * @param string $password
     * @param string $salt     At least 16 bytes
     * @param int $ops         Operation count (recommend 100000+)
     * @param int $keyLen      Output key length (32 for secretbox)
     * @return string Derived key
     */
    public static function deriveKey(string $password, string $salt, int $ops = 100000, int $keyLen = 32): string
    {
        self::ensureSodium();
        return sodium_crypto_pwhash_str($password, $salt, $ops, SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE, $keyLen);
    }

    /**
     * Verify a derived key.
     */
    public static function verifyKey(string $password, string $hash): bool
    {
        self::ensureSodium();
        return sodium_crypto_pwhash_str_verify($hash, $password);
    }

    /**
     * Generate a random token.
     */
    public static function randomToken(int $bytes = 32): string
    {
        self::ensureSodium();
        return bin2hex(sodium_randombytes_buf($bytes));
    }

    /**
     * Check if sodium extension is available.
     */
    public static function isSupported(): bool
    {
        return extension_loaded('sodium') || class_exists('\Sodium\crypto_secretbox_KEYBYTES');
    }

    /**
     * Ensure sodium is available.
     */
    protected static function ensureSodium(): void
    {
        if (!self::isSupported()) {
            throw new \RuntimeException('libsodium extension is required but not available. Install ext-sodium or paragonie/sodium_compat.');
        }
    }

    /**
     * Normalize a key to raw binary (handles hex-encoded keys).
     */
    protected static function normalizeKey(string $key): string
    {
        if (strlen($key) === 64 && ctype_xdigit($key)) {
            $decoded = hex2bin($key);
            if ($decoded !== false && strlen($decoded) === 32) {
                return $decoded;
            }
        }
        if (strlen($key) === 32) {
            return $key;
        }
        throw new \InvalidArgumentException('Key must be 32 bytes (raw or 64-char hex)');
    }
}
