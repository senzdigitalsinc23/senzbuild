<?php
require 'vendor/autoload.php';
use App\Core\Vault;

// Simulate exact CLI flow
Vault::init();
Vault::set('db.password', 'supersecret');

// Now simulate get
$ref = new ReflectionClass(Vault::class);

$readMethod = $ref->getMethod('readVaultEntry');
$readMethod->setAccessible(true);
$encrypted = $readMethod->invoke(null, 'db.password');
echo "readVaultEntry: " . ($encrypted ? 'found' : 'null') . "\n";
if ($encrypted) {
    echo "ct len: " . strlen($encrypted['ciphertext']) . "\n";
    echo "iv len: " . strlen($encrypted['iv']) . "\n";
}

$decryptMethod = $ref->getMethod('decrypt');
$decryptMethod->setAccessible(true);
if ($encrypted) {
    $plaintext = $decryptMethod->invoke(null, $encrypted['ciphertext'], $encrypted['iv']);
    echo "decrypt: " . var_export($plaintext, true) . "\n";
    echo "error: " . openssl_error_string() . "\n";
}

echo "has: " . (Vault::has('db.password') ? 'true' : 'false') . "\n";
echo "get: " . var_export(Vault::get('db.password'), true) . "\n";
