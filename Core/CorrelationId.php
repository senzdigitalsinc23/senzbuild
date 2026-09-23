<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Correlation ID manager.
 *
 * Generates or extracts a unique request ID and threads it through the
 * entire request lifecycle — available in logs, middleware, and services.
 *
 * Usage:
 *   CorrelationId::init($request);         // call once per request
 *   CorrelationId::get();                  // get current ID (returns uuid v4)
 *   CorrelationId::log('something happened'); // log with auto-injected ID
 */
class CorrelationId
{
    private static ?string $id = null;

    private const HEADER = 'X-Correlation-ID';

    /**
     * Initialize the correlation ID from the current request.
     * Uses the X-Correlation-ID header if present, otherwise generates a new one.
     */
    public static function init(?Request $request = null): string
    {
        if (self::$id !== null) {
            return self::$id;
        }

        // Check request header first
        if ($request !== null && $request->hasHeader(self::HEADER)) {
            $headerValue = $request->getHeaderLine(self::HEADER);
            if (!empty($headerValue)) {
                self::$id = $headerValue;
                return self::$id;
            }
        }

        // Check global server params (for CLI or non-PSR flow)
        $headerValue = ($_SERVER[self::HEADER] ?? null);
        if (!empty($headerValue)) {
            self::$id = $headerValue;
            return self::$id;
        }

        // Generate new UUID v4
        self::$id = self::generate();
        return self::$id;
    }

    /**
     * Get the current correlation ID.
     * Auto-initializes if not yet set.
     */
    public static function get(): string
    {
        if (self::$id === null) {
            self::init();
        }
        return self::$id;
    }

    /**
     * Set the correlation ID explicitly (for testing or custom flows).
     */
    public static function set(string $id): void
    {
        self::$id = $id;
    }

    /**
     * Reset the correlation ID (call between requests in CLI / tests).
     */
    public static function reset(): void
    {
        self::$id = null;
    }

    /**
     * Generate a new UUID v4 string.
     */
    public static function generate(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Attach the correlation ID to a PSR-3 logger context.
     * Returns the context array ready to be merged with log context.
     */
    public static function logContext(): array
    {
        return ['correlation_id' => self::get()];
    }
}
