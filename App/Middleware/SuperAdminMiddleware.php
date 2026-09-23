<?php
declare(strict_types=1);


namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

class SuperAdminMiddleware
{
    public function handle(Request $request, Response $response, callable $next): Response
    {
        // Check if user is logged in
        $userId = Session::get('user_id');
        if (!$userId) {
            $response->json([
                'success' => false,
                'message' => 'Unauthorized. Please login.'
            ], 401);
            return $response;
        }

        // Check if user is super admin
        $isSuperAdmin = Session::get('is_super_admin');
        if ($isSuperAdmin !== '1' && $isSuperAdmin !== 1 && $isSuperAdmin !== true) {
            $response->json([
                'success' => false,
                'message' => 'Forbidden. Super admin access required.'
            ], 403);
            return $response;
        }

        return $next($request, $response);
    }
}
