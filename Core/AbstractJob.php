<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Abstract base class for all queued jobs.
 *
 * Provides sensible defaults for maxTries() and backoff(),
 * making it easy to create jobs that auto-retry on failure.
 *
 * Usage:
 *   class MyJob extends AbstractJob
 *   {
 *       public function __construct(int $userId) { ... }
 *
 *       public function handle(): void
 *       {
 *           // job logic
 *       }
 *   }
 *
 * Override maxTries() or backoff() when needed.
 */
abstract class AbstractJob implements Job, ShouldQueue
{
    /**
     * Default max retry attempts before moving to DLQ.
     */
    protected int $maxTries = 3;

    /**
     * Backoff strategy: 'exponential', 'linear', or an array of seconds.
     */
    protected string|array $backoff = 'exponential';

    public function maxTries(): int
    {
        return $this->maxTries;
    }

    public function backoff(): string|array
    {
        return $this->backoff;
    }

    /**
     * Set max retries from outside the constructor.
     */
    public function setMaxTries(int $tries): self
    {
        $this->maxTries = $tries;
        return $this;
    }

    /**
     * Set backoff strategy.
     */
    public function setBackoff(string|array $backoff): self
    {
        $this->backoff = $backoff;
        return $this;
    }
}
