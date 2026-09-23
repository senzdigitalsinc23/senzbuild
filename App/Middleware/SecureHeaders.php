<?php
declare(strict_types=1);

namespace App\Middleware;

class SecureHeaders
{
    public static function send(): void
    {
        $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        $frontendOrigin = $_ENV['CORS_ALLOWED_ORIGINS'] ?? 'http://localhost:3000';

        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer-when-downgrade');
        header('X-XSS-Protection: 0');

        $csp = "default-src 'self'; "
            . "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://unpkg.com https://cdn.jsdelivr.net; "
            . "style-src 'self' 'unsafe-inline' https://unpkg.com https://cdn.jsdelivr.net; "
            . "img-src 'self' data: blob:; "
            . "font-src 'self' https://unpkg.com https://cdn.jsdelivr.net; "
            . "connect-src 'self' $frontendOrigin https://api.paystack.co; "
            . "object-src 'none'; base-uri 'self'; frame-ancestors 'none'";
        header("Content-Security-Policy: $csp");

        if ($isHttps) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
    }
}
