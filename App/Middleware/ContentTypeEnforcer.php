<?php
declare(strict_types=1);
// app/Middleware/ContentTypeEnforcerMiddleware.php
namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

class ContentTypeEnforcer
{
    public function handle(Request $request, Response $response, callable $next): Response
    {
        $method = strtoupper($request->getMethod());
        if (in_array($method, ['POST','PUT','PATCH'])) {
            $ctype = $_SERVER['CONTENT_TYPE'] ?? '';
            // Accept JSON or form-encoded only
            if (
                stripos($ctype, 'application/json') === false &&
                stripos($ctype, 'application/x-www-form-urlencoded') === false &&
                stripos($ctype, 'multipart/form-data') === false
            ) {
                $response->setStatusCode(415);
                $response->setHeader('Content-Type', 'application/json');
                $response->setContent(json_encode(['error' => 'Unsupported Media Type']));
                return $response;
            }
        }

        return $next($request, $response);
    }
}
