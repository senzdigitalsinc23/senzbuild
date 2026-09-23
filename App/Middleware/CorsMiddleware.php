<?php
declare(strict_types=1);

// app/Middleware/CorsMiddleware.php
namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

class CorsMiddleware
{
    private array $allowedOrigins;
    private array $allowedMethods;
    private array $allowedHeaders;

    public function __construct()
    {
        $corsOrigins = $_ENV['CORS_ALLOWED_ORIGINS'] ?? '';
        // Remove quotes if present and split by comma
        $corsOrigins = trim($corsOrigins, '"\'');
        $this->allowedOrigins = array_map(function($origin) {
            return trim(trim($origin, '"\''));
        }, explode(',', $corsOrigins));
        $this->allowedMethods = ['GET','POST','PUT','PATCH','DELETE','OPTIONS'];
        $this->allowedHeaders = ['Content-Type','Authorization','X-CSRF-TOKEN','X-API-Key','X-API-KEY'];
    }

    public function handle(Request $request, Response $response, callable $next): Response
    {
        // CORS headers are already set at the bootstrap level (public/index.php).
        // This middleware is kept for compatibility but does nothing extra.
        return $next($request, $response);
    }
}
