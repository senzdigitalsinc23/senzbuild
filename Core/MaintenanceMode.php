<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Maintenance Mode — gracefully take the application down for maintenance.
 *
 * Usage:
 *   MaintenanceMode::enable();           // put app in maintenance
 *   MaintenanceMode::disable();          // take app out of maintenance
 *   MaintenanceMode::enabled();          // check status
 *
 * Middleware checks this on every request and returns 503 if enabled.
 */
class MaintenanceMode
{
    protected static bool $enabled = false;
    protected static ?int $startedAt = null;
    protected static string $message = 'Service is under maintenance. Please try again later.';

    /**
     * Enable maintenance mode.
     */
    public static function enable(string $message = 'Service is under maintenance. Please try again later.'): void
    {
        self::$enabled = true;
        self::$startedAt = time();
        self::$message = $message;
        self::writeState();
    }

    /**
     * Disable maintenance mode.
     */
    public static function disable(): void
    {
        self::$enabled = false;
        self::$startedAt = null;
        self::writeState();
    }

    /**
     * Check if maintenance mode is active.
     */
    public static function enabled(): bool
    {
        // Also check file-based state (survives restarts)
        $fileState = self::readState();
        if ($fileState !== null) {
            self::$enabled = $fileState;
            self::$startedAt = self::$startedAt ?? time();
        }
        return self::$enabled;
    }

    /**
     * Get the maintenance message.
     */
    public static function getMessage(): string
    {
        return self::$message;
    }

    /**
     * Get the timestamp when maintenance started.
     */
    public static function getStartedAt(): ?int
    {
        return self::$startedAt;
    }

    /**
     * Check if a URI should bypass maintenance mode (e.g., health checks).
     */
    public static function shouldBypass(string $uri): bool
    {
        $bypassUris = [];
        try {
            $bypassUris = Config::get('app.maintenance_bypass', ['/health', '/ping', '/api/v1/health']);
        } catch (\Exception $e) {
            $bypassUris = ['/health', '/ping', '/api/v1/health'];
        }
        foreach ($bypassUris as $pattern) {
            if (str_starts_with($uri, $pattern)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Write maintenance state to storage file.
     */
    protected static function writeState(): void
    {
        $path = dirname(__DIR__) . '/storage/maintenance.json';
        $data = [
            'enabled'  => self::$enabled,
            'started_at' => self::$startedAt,
            'message'  => self::$message,
        ];
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
        chmod($path, 0644);
    }

    /**
     * Read maintenance state from storage file.
     */
    protected static function readState(): ?bool
    {
        $path = dirname(__DIR__) . '/storage/maintenance.json';
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode(file_get_contents($path), true);
        return $data['enabled'] ?? null;
    }
}

/**
 * Maintenance Mode Middleware — returns 503 when maintenance is active.
 */
class MaintenanceModeMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Response $response, callable $next): Response
    {
        if (MaintenanceMode::enabled() && !MaintenanceMode::shouldBypass($request->getPath())) {
            $response->setStatusCode(503);
            $response->setHeader('Content-Type', 'application/json');
            $response->setContent(json_encode([
                'success' => false,
                'message' => MaintenanceMode::getMessage(),
                'retry_after' => 60,
            ]));
            return $response;
        }

        return $next($request, $response);
    }
}
