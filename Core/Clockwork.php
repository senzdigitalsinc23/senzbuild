<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Clockwork Debugbar — developer debugging toolbar for non-production environments.
 *
 * Usage:
 *   // In bootstrap or service provider:
 *   Clockwork::boot();
 *
 *   // Automatic instrumentation:
 *   Clockwork::collect();
 */
class Clockwork
{
    protected static bool $enabled = false;
    protected static array $events = [];
    protected static array $queries = [];
    protected static array $logs = [];
    protected static array $memory = [];
    protected static float $startTime;
    protected static string $version = '1.0.0';

    /**
     * Boot Clockwork for the current request.
     */
    public static function boot(): void
    {
        if (!self::shouldEnable()) {
            return;
        }
        self::$enabled = true;
        self::$startTime = microtime(true);
        self::collect();
    }

    /**
     * Check if Clockwork should be enabled (only in non-prod).
     */
    public static function shouldEnable(): bool
    {
        return env('APP_DEBUG', false) === true
            || env('CLOCKWORK_ENABLED', false) === true;
    }

    /**
     * Collect request data automatically.
     */
    public static function collect(): void
    {
        self::$memory[] = [
            'memory_usage' => memory_get_usage(true),
            'peak_memory'  => memory_get_peak_usage(true),
            'time'         => microtime(true) - self::$startTime,
        ];

        // Register shutdown collector
        register_shutdown_function(function () {
            self::$memory[] = [
                'memory_usage' => memory_get_usage(true),
                'peak_memory'  => memory_get_peak_usage(true),
                'time'         => microtime(true) - self::$startTime,
            ];
            self::sendResponse();
        });
    }

    /**
     * Log a message to the debug toolbar.
     */
    public static function log(string $message, string $level = 'info', array $context = []): void
    {
        if (!self::$enabled) {
            return;
        }
        self::$logs[] = [
            'message'  => $message,
            'level'    => $level,
            'context'  => $context,
            'time'     => microtime(true) - self::$startTime,
            'trace'    => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5),
        ];
    }

    /**
     * Record a database query.
     */
    public static function query(string $sql, array $bindings = [], float $duration = 0): void
    {
        if (!self::$enabled) {
            return;
        }
        self::$queries[] = [
            'sql'      => $sql,
            'bindings' => $bindings,
            'duration' => $duration,
            'time'     => microtime(true) - self::$startTime,
        ];
    }

    /**
     * Record an event.
     */
    public static function event(string $name, array $payload = []): void
    {
        if (!self::$enabled) {
            return;
        }
        self::$events[] = [
            'name'    => $name,
            'payload' => $payload,
            'time'    => microtime(true) - self::$startTime,
        ];
    }

    /**
     * Record a custom action/timing.
     */
    public static function action(string $name, callable $callback, array $params = []): mixed
    {
        $start = microtime(true);
        try {
            $result = $callback(...$params);
            self::event("action.{$name}", ['duration' => microtime(true) - $start]);
            return $result;
        } catch (\Throwable $e) {
            self::event("action.{$name}.error", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Send the Clockwork response to the browser.
     */
    protected static function sendResponse(): void
    {
        if (!self::$enabled) {
            return;
        }

        $data = [
            'version'     => self::$version,
            'identifier'  => bin2hex(random_bytes(16)),
            'timestamp'   => time(),
            'startTime'   => self::$startTime,
            'stopTime'    => microtime(true),
            'memory'      => self::$memory,
            'queries'     => self::$queries,
            'logs'        => self::$logs,
            'events'     => self::$events,
            'server'      => $_SERVER,
            'request'     => [
                'uri'       => $_SERVER['REQUEST_URI'] ?? '/',
                'method'    => $_SERVER['REQUEST_METHOD'] ?? 'GET',
                'headers'   => getallheaders(),
            ],
            'application' => [
                'name'  => Config::get('app.name', 'Framework'),
                'env'   => Config::get('app.env', 'local'),
                'debug' => Config::get('app.debug', false),
            ],
        ];

        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT);
    }

    /**
     * Reset all collected data.
     */
    public static function reset(): void
    {
        self::$events = [];
        self::$queries = [];
        self::$logs = [];
        self::$memory = [];
    }
}

/**
 * Clockwork middleware — injects debug toolbar into responses.
 */
class ClockworkMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Response $response, callable $next): Response
    {
        if (!Clockwork::shouldEnable()) {
            return $next($request, $response);
        }

        $response = $next($request, $response);

        // Inject Clockwork data into HTML responses
        $content = $response->getContent();
        if (str_contains($content, '</body>')) {
            $content = preg_replace(
                '/<\/body>/i',
                '<script src="/vendor/clockwork/clockwork.js"></script>' .
                '<link rel="stylesheet" href="/vendor/clockwork/clockwork.css">' .
                '<div id="clockwork" data-clockwork-data="{}"></div>' .
                '</body>',
                $content,
                1
            );
            $response->setContent($content);
        }

        // Also expose data via X-Clockwork header for the toolbar
        $clockworkData = Clockwork::collectForHeader();
        $response->setHeader('X-Clockwork-Id', $clockworkData['id']);
        $response->setHeader('X-Clockwork-Version', $clockworkData['version']);

        return $response;
    }

    protected static function collectForHeader(): array
    {
        return [
            'id'      => bin2hex(random_bytes(16)),
            'version' => Clockwork::VERSION,
        ];
    }
}
