<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTMiddleware implements MiddlewareInterface
{
    private string $secret;

    /**
     * @param string|null $secret JWT secret key
     * @throws \RuntimeException If JWT_SECRET is not configured
     */
    public function __construct(?string $secret = null)
    {
        // SECURITY: Never use default JWT secrets - enforce configuration
        if ($secret === null) {
            if (empty($_ENV['JWT_SECRET'])) {
                throw new \RuntimeException(
                    'JWT_SECRET environment variable is not configured. ' .
                    'Please set a strong secret key in your .env file.'
                );
            }
            $secret = $_ENV['JWT_SECRET'];
        }
        
        $this->secret = $secret;
    }

    public function handle(Request $request, Response $response, callable $next): Response
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
            $response->setStatusCode(401);
            $response->setHeader('Content-Type', 'application/json');
            $response->setContent(json_encode(['error' => 'Unauthorized']));
            return $response;
        }

        $token = $m[1];

        try {
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));

            // Optionally attach the user to the request (if your Request supports it)
            if (method_exists($request, 'setUser') && isset($decoded->sub)) {
                if (class_exists(\App\Models\User::class) && method_exists(\App\Models\User::class, 'find')) {
                    $user = \App\Models\User::find((int)$decoded->sub);
                    if ($user && method_exists($request, 'setUser')) {
                        $request->setUser($user);
                    }
                }
            }
        } catch (\Throwable $e) {
            $response->setStatusCode(401);
            $response->setHeader('Content-Type', 'application/json');
            $response->setContent(json_encode(['error' => 'Invalid or expired token']));
            return $response;
        }

        return $next($request, $response);
    }
}
