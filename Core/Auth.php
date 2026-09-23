<?php
declare(strict_types=1);
namespace App\Core;

use App\Models\User;
use App\Core\Database\Database;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Auth
{
    protected static ?User $user = null;

    // 🚀 Session-based login
    public static function login(User $user): void
    {
        $_SESSION['user_id'] = $user->id;
        self::$user = $user;
    }

    public static function logout(): void
    {
        unset($_SESSION['user_id']);
        self::$user = null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function user(): ?User
    {
        if (self::$user) {
            return self::$user;
        }
        if (isset($_SESSION['user_id'])) {
            self::$user = User::find($_SESSION['user_id']);
        }
        return self::$user;
    }

    // 🚀 JWT-based login
    public static function generateToken(User $user): string
    {
        $payload = [
            'sub' => $user->id,
            'iat' => time(),
            'exp' => time() + (60 * 60), // 1 hour expiry
        ];

        return JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');
    }

    public static function userFromToken(string $token): ?User
    {
        try {
            $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
            return User::find($decoded->sub);
        } catch (\Exception $e) {
            return null;
        }
    }
}
