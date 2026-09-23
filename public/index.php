<?php

// ── CORS — must be the absolute first thing, before any output or require ────
(function () {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    // Read allowed origins from .env without full framework boot
    $allowed = ['http://localhost:3000'];
    $envFile  = dirname(__DIR__) . '/.env';
    if ($origin && file_exists($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'CORS_ALLOWED_ORIGINS=')) {
                $val     = trim(substr($line, strlen('CORS_ALLOWED_ORIGINS=')), " \t\"'");
                $allowed = array_map('trim', explode(',', $val));
                break;
            }
        }
    }

    $isAllowed = !$origin || in_array($origin, $allowed, true) || in_array('*', $allowed, true);

    if ($isAllowed && $origin) {
        header('Access-Control-Allow-Origin: ' . $origin);
    } elseif (!$origin) {
        // Non-browser request (curl, Postman) — allow
        header('Access-Control-Allow-Origin: *');
    }

    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-TOKEN, X-API-Key, X-API-KEY');
    header('Vary: Origin');

    // Preflight — respond immediately, no further processing needed
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
})();
// ─────────────────────────────────────────────────────────────────────────────

require __DIR__ . '/../vendor/autoload.php';

// Clear opcache so code changes take effect
if (function_exists('opcache_reset')) { opcache_reset(); }

// Never output PHP warnings/notices into the response body — they corrupt JSON
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
// Still log them
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

// Load .env before any config (use Dotenv if installed, else parse .env manually)
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile) && is_readable($envFile)) {
    if (class_exists(\Dotenv\Dotenv::class)) {
        $dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__));
        $dotenv->safeLoad();
    } else {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                [$name, $value] = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                if ($name !== '') {
                    $_ENV[$name] = $value;
                    putenv("$name=$value");
                }
            }
        }
    }
}

// Validate environment configuration
try {
    $validator = new \App\Core\EnvironmentValidator();
    $validator->validateOrFail();
} catch (\RuntimeException $e) {
    // In production, show generic error
    if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
        http_response_code(500);
        die('Application configuration error. Please contact support.');
    }
    // In development, show detailed error
    die($e->getMessage());
}

// Send secure HTTP headers
\App\Middleware\SecureHeaders::send();

use App\Core\Config;
use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;

Config::load(dirname(__DIR__) . '/config');

// Basic PHP error display based on env/config
if (Config::get('app.env') === 'production') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
} elseif (!empty(Config::get('app.display_errors')) && Config::get('app.display_errors') === 'true') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}

if (session_status() === PHP_SESSION_NONE) {
    // Secure cookie & strict session settings
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? null) == 443;

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax', // consider 'Strict' for non-3rd-party flows
    ]);

    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $secure ? '1' : '0');
    ini_set('session.use_only_cookies', '1');
    session_name('app_session');

    session_start();
    // Rotate session ID after login or privilege changes
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
    }
}


// public/index.php (top-level front controller)
set_exception_handler(function (\Throwable $e) {
    $isApi = str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/');

    // Setup tools for handling the error
    $container = new \App\Core\Container();
    $logger = new \App\Core\Log\Logger(dirname(__DIR__) . '/storage/logs/app.log');
    $mapper = new \App\Core\ExceptionMapper();

    // Log the error with full context
    $logger->error($e->getMessage(), [
        'exception' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);

    // Map the exception to a standardized API response
    $apiEx = $mapper->map($e);
    $code = $apiEx->getStatusCode();

    http_response_code($code);

    // Discard any partial output captured in active output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }

    if (!empty(Config::get('app.debug')) && Config::get('app.debug') === 'true') {
        // Dev mode — show detailed error
        if ($isApi) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $apiEx->getErrorCode(),
                'message' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTrace()
            ], JSON_PRETTY_PRINT);
        } else {
            echo "<pre>" . htmlspecialchars((string)$e, ENT_QUOTES) . "</pre>";
        }
    } else {
        // Production mode — never leak internals
        if ($isApi) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $apiEx->getErrorCode(),
                'message' => $apiEx->getMessage()
            ]);
        } else {
            echo "Something went wrong. Please try again later.";
        }
    }
});

// Boot container
$container = new Container();

// Register Modular Service Providers
$container->register(\App\Providers\CoreServiceProvider::class);
$container->register(\App\Providers\DatabaseServiceProvider::class);
$container->register(\App\Providers\RepositoryServiceProvider::class);
$container->register(\App\Providers\ServiceServiceProvider::class);

// Init router
$router = new Router($container);

// Load routes
require __DIR__ . '/../routes/web.php';
require __DIR__ . '/../routes/api.php';

// Dispatch request
$request = $container->resolve(Request::class);
$response = $container->resolve(Response::class);

$response = $router->dispatch($request, $response);

// Avoid double-send when controllers already called json() which sends output
if (!headers_sent()) {
    $response->send();
}
