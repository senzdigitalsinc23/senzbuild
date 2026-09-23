<?php
declare(strict_types=1);

namespace App\Core;

interface Job
{
    public function handle(): void;

    /**
     * Get the number of times the job may be attempted.
     */
    public function maxTries(): int;

    /**
     * Get the backoff interval (in seconds) for retries.
     * Return 'exponential' for exponential backoff, or an int/array for custom intervals.
     */
    public function backoff(): int|array|string;
}
