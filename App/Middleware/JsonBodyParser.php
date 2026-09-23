<?php
declare(strict_types=1);
// app/Middleware/JsonBodyParserMiddleware.php
namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

class JsonBodyParser
{
    public function handle(Request $request, Response $response, callable $next): Response
    {
        $method = strtoupper($request->getMethod());
        if (in_array($method, ['POST','PUT','PATCH'])) {
            $ctype = $_SERVER['CONTENT_TYPE'] ?? '';
            if (stripos($ctype, 'application/json') !== false) {
                $raw = file_get_contents('php://input');
                $data = json_decode($raw, true);
                $_POST = is_array($data) ? $data : [];
            }
        }
        return $next($request, $response);
    }
}
