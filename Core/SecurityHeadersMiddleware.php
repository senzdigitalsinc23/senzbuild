<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Security Headers middleware — adds HSTS, CSP, and other security headers.
 *
 * Usage:
 *   // In router or service provider:
 *   $router->middleware([\App\Core\SecurityHeadersMiddleware::class]);
 *
 *   // Or set globally:
 *   $app->container->singleton(\App\Core\SecurityHeadersMiddleware::class);
 */
class SecurityHeadersMiddleware implements MiddlewareInterface
{
    protected array $config;

    public function __construct()
    {
        $this->config = Config::get('security.headers', []);
    }

    public function handle(Request $request, Response $response, callable $next): Response
    {
        $response = $next($request, $response);

        // HSTS — Strict-Transport-Security
        if ($this->config['hsts'] ?? false) {
            $maxAge = $this->config['hsts_max_age'] ?? 31536000;
            $includeSubDomains = $this->config['hsts_include_subdomains'] ?? true;
            $preload = $this->config['hsts_preload'] ?? false;

            $header = "max-age={$maxAge}";
            if ($includeSubDomains) {
                $header .= "; includeSubDomains";
            }
            if ($preload) {
                $header .= "; preload";
            }
            $response->setHeader('Strict-Transport-Security', $header);
        }

        // CSP — Content-Security-Policy
        $csp = $this->config['csp'] ?? [];
        if (!empty($csp)) {
            $directives = [];
            foreach ($csp as $directive => $values) {
                if (is_array($values)) {
                    $directives[] = "{$directive} " . implode(' ', $values);
                } elseif ($values !== null) {
                    $directives[] = "{$directive} {$values}";
                }
            }
            if (!empty($directives)) {
                $response->setHeader('Content-Security-Policy', implode('; ', $directives));
            }
        }

        // X-Frame-Options
        $response->setHeader('X-Frame-Options', $this->config['x_frame_options'] ?? 'DENY');

        // X-Content-Type-Options
        $response->setHeader('X-Content-Type-Options', 'nosniff');

        // X-XSS-Protection
        $response->setHeader('X-XSS-Protection', '1; mode=block');

        // Referrer-Policy
        $response->setHeader('Referrer-Policy', $this->config['referrer_policy'] ?? 'strict-origin-when-cross-origin');

        // Permissions-Policy
        $permissions = $this->config['permissions_policy'] ?? [];
        if (!empty($permissions)) {
            $parts = [];
            foreach ($permissions as $feature => $value) {
                $parts[] = "{$feature}={$value}";
            }
            $response->setHeader('Permissions-Policy', implode(', ', $parts));
        }

        // Cache-Control for sensitive pages
        if (!$this->config['cache_sensitive'] ?? false) {
            $response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, proxy-revalidate');
            $response->setHeader('Pragma', 'no-cache');
            $response->setHeader('Expires', '0');
        }

        return $response;
    }
}
