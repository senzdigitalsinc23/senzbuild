#!/usr/bin/env php
<?php
/**
 * Vault CLI tool — encrypt/decrypt/delete secrets.
 *
 * Usage:
 *   php tools/vault.php set database.password "my-secret"
 *   php tools/vault.php get database.password
 *   php tools/vault.php has database.password
 *   php tools/vault.php forget database.password
 *   php tools/vault.php flush
 *   php tools/vault.php status
 *   php tools/vault.php key                       # show current key fingerprint
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Vault;

define('BASE_PATH', __DIR__ . '/..');

$argv = $GLOBALS['argv'];
array_shift($argv); // remove script name

if (empty($argv) || in_array('--help', $argv) || in_array('-h', $argv)) {
    echo <<<'USAGE'
Vault CLI — Encrypted Secrets Manager

Usage:
  php tools/vault.php set   <key> <value>    Encrypt and store a secret
  php tools/vault.php get   <key>            Decrypt and print a secret
  php tools/vault.php has   <key>            Check if a secret exists
  php tools/vault.php forget <key>           Remove a secret from the vault
  php tools/vault.php flush                  Delete ALL secrets (destructive)
  php tools/vault.php status                 Show vault info and key fingerprint
  php tools/vault.php key                    Print the master key fingerprint only

USAGE;
    exit(0);
}

$action = array_shift($argv);
$key = array_shift($argv);
$value = $argv[0] ?? null;

switch ($action) {
    case 'set':
        if ($key === null || $value === null) {
            fwrite(STDERR, "Error: 'set' requires <key> and <value>\n");
            exit(1);
        }
        Vault::init();
        Vault::set($key, $value);
        echo "Secret '{$key}' encrypted and stored.\n";
        break;

    case 'get':
        if ($key === null) {
            fwrite(STDERR, "Error: 'get' requires <key>\n");
            exit(1);
        }
        Vault::init();
        // Debug: check what's in the vault
        $ref = new \ReflectionClass(Vault::class);
        $readMethod = $ref->getMethod('readVaultEntry');
        $readMethod->setAccessible(true);
        $encrypted = $readMethod->invoke(null, $key);
        fwrite(STDERR, "DEBUG readVaultEntry: " . ($encrypted ? 'found' : 'null') . "\n");
        if ($encrypted) {
            fwrite(STDERR, "DEBUG ct len: " . strlen($encrypted['ciphertext']) . "\n");
            fwrite(STDERR, "DEBUG iv len: " . strlen($encrypted['iv']) . "\n");
            $decryptMethod = $ref->getMethod('decrypt');
            $decryptMethod->setAccessible(true);
            $plaintext = $decryptMethod->invoke(null, $encrypted['ciphertext'], $encrypted['iv']);
            fwrite(STDERR, "DEBUG decrypt: " . var_export($plaintext, true) . "\n");
            fwrite(STDERR, "DEBUG error: " . openssl_error_string() . "\n");
        }
        if (!Vault::has($key)) {
            fwrite(STDERR, "Secret '{$key}' not found in vault.\n");
            exit(1);
        }
        $result = Vault::get($key);
        fwrite(STDERR, "DEBUG get result: " . var_export($result, true) . "\n");
        echo $result !== null ? $result : '';
        break;

    case 'has':
        if ($key === null) {
            fwrite(STDERR, "Error: 'has' requires <key>\n");
            exit(1);
        }
        Vault::init();
        echo Vault::has($key) ? "true\n" : "false\n";
        break;

    case 'forget':
        if ($key === null) {
            fwrite(STDERR, "Error: 'forget' requires <key>\n");
            exit(1);
        }
        Vault::init();
        if (Vault::has($key)) {
            Vault::forget($key);
            echo "Secret '{$key}' removed.\n";
        } else {
            echo "Secret '{$key}' not found.\n";
        }
        break;

    case 'flush':
        Vault::init();
        Vault::flush();
        echo "All vault secrets wiped.\n";
        break;

    case 'status':
        Vault::init();
        $path = BASE_PATH . '/storage/vault/secrets.json';
        $exists = is_file($path);
        echo "Vault status:\n";
        echo "  Directory: " . BASE_PATH . '/storage/vault/' . "\n";
        echo "  File: {$path}\n";
        echo "  Exists: " . ($exists ? 'Yes' : 'No') . "\n";
        if ($exists) {
            $size = filesize($path);
            echo "  Size: {$size} bytes\n";
            $data = json_decode(file_get_contents($path), true);
            echo "  Secrets count: " . count($data) . "\n";
        }
        $key = Vault::getMasterKey();
        $fp = $key !== null ? substr(hash('sha256', $key), 0, 16) : 'none';
        echo "  Key fingerprint: {$fp}\n";
        break;

    case 'key':
        Vault::init();
        $k = Vault::getMasterKey();
        echo $k !== null ? substr(hash('sha256', $k), 0, 16) : "none\n";
        break;

    default:
        fwrite(STDERR, "Unknown action: {$action}\n");
        exit(1);
}

exit(0);
