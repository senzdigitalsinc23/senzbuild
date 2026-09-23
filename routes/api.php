<?php

use App\Controllers\Api\HealthController;
use App\Controllers\Api\DocumentationController;
use App\Controllers\Api\v1\AuthController;
use App\Controllers\Api\v1\GraphQLController;
use App\Controllers\Api\v1\AuditController;

use App\Middleware\CompressionMiddleware;
use App\Middleware\RequestTrackingMiddleware;
use App\Middleware\ApiVersionMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\WAFMiddleware;
use App\Middleware\RateLimiter;
use App\Middleware\CorsMiddleware;
use App\Middleware\FeatureGateMiddleware;
use App\Middleware\SecurityHeaders;
use App\Middleware\ContentTypeEnforcer;
use App\Middleware\JsonBodyParser;
use App\Middleware\AuditMiddleware;
use App\Middleware\APIKeyMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\BruteForceLockoutMiddleware;
use App\Middleware\CorrelationIdMiddleware;
use App\Middleware\RateLimitHeadersMiddleware;
use App\Middleware\IdempotencyMiddleware;
use App\Middleware\ResponseCacheMiddleware;



// Global API middleware (order matters)
$router->middleware([
    CompressionMiddleware::class,
    RequestTrackingMiddleware::class,
    ApiVersionMiddleware::class,
    CsrfMiddleware::class,
    WAFMiddleware::class,
    RateLimiter::class,
    CorsMiddleware::class,
    FeatureGateMiddleware::class,
    SecurityHeaders::class,
    ContentTypeEnforcer::class,
    JsonBodyParser::class,
    AuditMiddleware::class,
    CorrelationIdMiddleware::class,       // injects X-Correlation-ID on every response
    RateLimitHeadersMiddleware::class,    // X-RateLimit-* headers on every response
    IdempotencyMiddleware::class,         // deduplicates mutating requests by Idempotency-Key header
    ResponseCacheMiddleware::class,       // caches GET responses for anonymous users (TTL configurable)
]);

// Health check endpoints (no authentication required)
$router->getApi('v1', '/health', [HealthController::class, 'check'], []);
$router->getApi('v1', '/ping', [HealthController::class, 'ping'], []);

// Config — API key required, no user auth
$router->getApi('v1', '/config', [ConfigController::class, 'index'], [APIKeyMiddleware::class]);

// v1 Auth
$router->getApi('v1', '/mdware/auth/csrf', [CsrfController::class, 'token'], [RateLimiter::class]);
$router->postApi('v1', '/register', [AuthController::class, 'register'], [APIKeyMiddleware::class, AuthMiddleware::class, RateLimiter::class, BruteForceLockoutMiddleware::class]);
$router->postApi('v1', '/login',  [AuthController::class, 'login'],   [APIKeyMiddleware::class, AuthMiddleware::class, RateLimiter::class, BruteForceLockoutMiddleware::class]);
$router->postApi('v1', '/refresh', [AuthController::class, 'refresh'], [RateLimiter::class]);
$router->postApi('v1', '/logout',  [AuthController::class, 'logout'],  [AuthMiddleware::class]);
$router->postApi('v1', '/logout-all', [AuthController::class, 'logoutAll'], [AuthMiddleware::class]);
$router->getApi('v1', '/me',       [AuthController::class, 'me'],      [AuthMiddleware::class]);


$router->postApi('v1', '/test/mail', [TestController::class, 'mail'], []);


// Swagger/OpenAPI documentation routes
$router->getApi('v1', '/swagger', [DocumentationController::class, 'index']);
$router->getApi('v1', '/docs', [DocumentationController::class, 'docs']);

// GraphQL endpoint
$router->postApi('v1', '/graphql', [GraphQLController::class, 'execute'], [JsonBodyParser::class]);

// Audit logs
$router->getApi('v1', '/audit/logs', [AuditController::class, 'index'], [AuthMiddleware::class]);
$router->getApi('v1', '/audit/logs/export', [AuditController::class, 'export'], [AuthMiddleware::class]);
$router->getApi('v1', '/audit/logs/{id}', [AuditController::class, 'show'], [AuthMiddleware::class]);
