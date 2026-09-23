<?php
declare(strict_types=1);

namespace App\Core;

/**
 * In-Memory Array Queue Driver — for testing and sync processing.
 *
 * Jobs are stored in a PHP array. No persistence between requests.
 */
class ArrayQueue
{
    protected static array $queues = [];
    protected static array $failed = [];

    /**
     * Push a job onto a queue.
     */
    public static function push(string $jobClass, array $data = [], string $queue = 'default'): int
    {
        $payload = [
            'job_class'  => $jobClass,
            'data'       => $data,
            'dispatched' => time(),
        ];
        self::$queues[$queue][] = $payload;
        return count(self::$queues[$queue]);
    }

    /**
     * Pop the next job from a queue.
     */
    public static function pop(string $queue = 'default'): ?array
    {
        if (empty(self::$queues[$queue])) {
            return null;
        }
        return array_shift(self::$queues[$queue]);
    }

    /**
     * Get queue length.
     */
    public static function size(string $queue = 'default'): int
    {
        return count(self::$queues[$queue] ?? []);
    }

    /**
     * Mark a job as failed.
     */
    public static function fail(string $jobClass, string $error, string $queue = 'default'): void
    {
        self::$failed[] = [
            'job_class' => $jobClass,
            'error'     => $error,
            'failed_at' => time(),
            'queue'     => $queue,
        ];
    }

    /**
     * Get failed jobs.
     */
    public static function failed(): array
    {
        return self::$failed;
    }

    /**
     * Clear all queues.
     */
    public static function flush(): void
    {
        self::$queues = [];
        self::$failed = [];
    }
}
