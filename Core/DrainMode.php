<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Zero-Downtime Drain Mode — gracefully stop queue workers during deployments.
 *
 * When enabled, the queue worker will finish current jobs but reject new ones.
 * Use with a deployment signal file.
 *
 * Usage:
 *   // In deployment script:
 *   touch storage/drain.pid  // signal workers to drain
 *   // Workers check this file each loop iteration
 *
 *   // Or via CLI:
 *   php bin/console queue:drain --signal
 */
class DrainMode
{
    protected static string $signalFile = 'storage/drain.pid';
    protected static bool $draining = false;
    protected static int $drainTimeout = 300; // 5 minutes max
    protected static int $startedAt = 0;

    public static function __static(): void
    {
        self::$signalFile = dirname(__DIR__) . '/storage/drain.pid';
    }

    public function __construct()
    {
        self::$signalFile = dirname(__DIR__) . '/storage/drain.pid';
    }

    /**
     * Signal the application to enter drain mode.
     */
    public static function signal(): void
    {
        self::$draining = true;
        self::$startedAt = time();
        file_put_contents(self::$signalFile, getmypid() . ':' . time());
    }

    /**
     * Cancel drain mode.
     */
    public static function cancel(): void
    {
        self::$draining = false;
        self::$startedAt = 0;
        if (is_file(self::$signalFile)) {
            unlink(self::$signalFile);
        }
    }

    /**
     * Check if the system is in drain mode.
     */
    public static function isDraining(): bool
    {
        // Also check signal file (survives process restart)
        if (!self::$draining && is_file(self::$signalFile)) {
            self::$draining = true;
            self::$startedAt = time() - 60;
        }
        return self::$draining;
    }

    /**
     * Check if drain timeout has been exceeded.
     */
    public static function isTimedOut(): bool
    {
        return self::$draining && (time() - self::$startedAt) > self::$drainTimeout;
    }

    /**
     * Get drain duration in seconds.
     */
    public static function getDuration(): int
    {
        if (!self::$draining) {
            return 0;
        }
        return time() - self::$startedAt;
    }

    /**
     * Get the signal file path.
     */
    public static function getSignalFile(): string
    {
        return self::$signalFile;
    }

    /**
     * Set drain timeout.
     */
    public static function setTimeout(int $seconds): void
    {
        self::$drainTimeout = $seconds;
    }
}
