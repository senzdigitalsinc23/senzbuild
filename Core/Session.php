<?php
declare(strict_types=1);

namespace App\Core;

class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function has(string $key): bool   // ✅ add this
    {
        return isset($_SESSION[$key]);
    }
    
    public static function get(string $key, $default = null)
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            session_destroy();
        }
    }

    public static function flash(string $key, ?string $message = null)
    {
        if ($message !== null) {
            // Set flash
            $_SESSION['flash'][$key] = $message;
        } else {
            // Retrieve and delete flash
            if (isset($_SESSION['flash'][$key])) {
                $msg = $_SESSION['flash'][$key];
                unset($_SESSION['flash'][$key]);
                return $msg;
            }
        }
        return null;
    }

    /**
     * Check if a flash message exists
     */
    public static function hasFlash(string $key): bool
    {
        return isset($_SESSION['flash'][$key]);
    }

     /** 🔒 CSRF Methods **/
    public static function token(): string
    {
        if (!self::has('_csrf_token')) {
            self::set('_csrf_token', bin2hex(random_bytes(32)));
        }
        return self::get('_csrf_token');
    }

    public static function verifyToken(string $token): bool
    {
        return hash_equals(self::get('_csrf_token', ''), $token);
    }
}
