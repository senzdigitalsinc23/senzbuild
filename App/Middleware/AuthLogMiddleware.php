<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\LoggingService;

class AuthLogMiddleware
{
    private LoggingService $loggingService;

    public function __construct(LoggingService $loggingService)
    {
        $this->loggingService = $loggingService;
    }

    public function handle(Request $request, Response $response, callable $next): Response
    {
        // Check if this is an authentication-related endpoint
        $path = $request->getPath();
        $method = $request->getMethod();
        
        if ($this->isAuthEndpoint($path)) {
            $this->logAuthEvent($request, $response);
        }
        
        return $next($request, $response);
    }

    private function isAuthEndpoint(string $path): bool
    {
        $authPaths = ['/login', '/logout', '/auth/login', '/auth/logout', '/api/auth'];
        
        foreach ($authPaths as $authPath) {
            if (strpos($path, $authPath) !== false) {
                return true;
            }
        }
        
        return false;
    }

    private function logAuthEvent(Request $request, Response $response): void
    {
        $path = $request->getPath();
        $method = $request->getMethod();
        $data = $request->getPost();
        
        // Determine event type
        $event = $this->getEventType($path, $method);
        
        // Determine event status based on response
        $status = $this->getEventStatus($response);
        
        // Get user ID if available
        $userId = $this->getUserIdFromRequest($request, $data);
        
        // Create details
        $details = [
            'endpoint' => $path,
            'method' => $method,
            'timestamp' => date('Y-m-d H:i:s'),
            'response_status' => $response->getStatusCode()
        ];
        
        if ($event === 'login' && isset($data['email'])) {
            $details['email'] = $data['email'];
        }
        
        $this->loggingService->logAuth(
            $event,
            $status,
            json_encode($details),
            $userId
        );
    }

    private function getEventType(string $path, string $method): string
    {
        if (strpos($path, 'login') !== false || strpos($path, 'signin') !== false) {
            return 'login';
        }
        
        if (strpos($path, 'logout') !== false || strpos($path, 'signout') !== false) {
            return 'logout';
        }
        
        return 'login'; // Default to login
    }

    private function getEventStatus(Response $response): string
    {
        $statusCode = $response->getStatusCode();
        
        if ($statusCode >= 200 && $statusCode < 300) {
            return 'success';
        }
        
        return 'failure';
    }

    private function getUserIdFromRequest(Request $request, array $data): ?string
    {
        // Try to get user ID from various sources
        if (isset($data['user_id'])) {
            return $data['user_id'];
        }
        
        if (isset($data['student_no'])) {
            return $data['student_no'];
        }
        
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
            return $_SESSION['user_id'];
        }
        
        return null;
    }
}
