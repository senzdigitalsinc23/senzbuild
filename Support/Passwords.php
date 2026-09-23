<?php
// app/Support/Passwords.php
namespace App\Support;

final class Passwords
{
    public static function hash(string $password): string
    {
        $algo  = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        $hash  = password_hash($password, $algo);
        if (!$hash) throw new \RuntimeException('Hashing failed');
        return $hash;
    }

    public static function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        return password_needs_rehash($hash, $algo);
    }
}
