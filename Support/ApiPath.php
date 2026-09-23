<?php
// app/Support/ApiPath.php
namespace Support;

final class ApiPath
{
    public static function isApi(): bool
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        return str_starts_with($uri, '/api/');
    }

    public static function isWhitelisted(): bool
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        // allow swagger if you want docs public:
        $whitelist = [
            '/swagger.json', '/docs',
            '/api/register', '/api/login'
        ];
        return in_array($uri, $whitelist, true);
    }
}
