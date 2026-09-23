<?php
declare(strict_types=1);


namespace App\Middleware;

use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Exceptions\AuthException;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Response $response, callable $next): Response
    {
        if (!Session::get('user')) {
            $authorization = $request->header('Authorization');

            if ($authorization && preg_match('/Bearer\s+(.+)$/i', $authorization, $matches)) {
                try {
                    $authService = new \App\Services\AuthService();
                    $userDTO     = $authService->validateToken($matches[1]);
                    if ($userDTO) {
                        Session::set('user', $userDTO->toArrayWithoutPassword());
                        Session::set('user_id', $userDTO->id);
                    }
                } catch (\Throwable $e) {
                    $logPath = dirname(__DIR__, 2) . '/storage/logs/api_debug.log';
                    file_put_contents($logPath, date('c') . " [AuthMiddleware] " . $e->getMessage() . "\n", FILE_APPEND);
                }
            }
        }
        if (!Session::get('user')) {
            $response->setStatusCode(401);
            $response->setHeader('Content-Type', 'application/json');
            $response->setContent(json_encode([
                'success' => false,
                'message' => 'Unauthorized'
            ]));
            return $response;
        }
        return $next($request, $response);
    }
}
