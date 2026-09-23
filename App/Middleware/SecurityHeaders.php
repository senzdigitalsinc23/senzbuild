<?php
declare(strict_types=1);

// app/Middleware/SecurityHeadersMiddleware.php
namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

class SecurityHeaders
{
    public function handle(Request $request, Response $response, callable $next): Response
    {
        // Only send HSTS if HTTPS (and let it preload once you’re ready)
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        $response->setHeader('X-Frame-Options', 'DENY');                    // clickjacking
        $response->setHeader('X-Content-Type-Options', 'nosniff');          // MIME sniffing
        $response->setHeader('Referrer-Policy', 'no-referrer-when-downgrade');
        $response->setHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        $response->setHeader('X-XSS-Protection', '0'); // modern browsers use CSP; legacy header off

        // Content Security Policy — adjust to your assets
        // Allow your own origin by default; add 'self' and needed CDNs carefully
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://unpkg.com https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://unpkg.com https://cdn.jsdelivr.net",
            "img-src 'self' data:",
            "font-src 'self' https://unpkg.com https://cdn.jsdelivr.net",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "frame-ancestors 'none'"
        ];
        $response->setHeader('Content-Security-Policy', implode('; ', $csp));

        if ($isHttps) {
            $response->setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }
        return $next($request, $response);
    }
}
