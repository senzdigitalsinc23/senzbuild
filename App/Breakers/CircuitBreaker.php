<?php
declare(strict_types=1);

namespace App\Breakers;

/**
 * Circuit breaker for external service calls.
 *
 * Prevents cascading failures by stopping requests to a failing service
 * after a threshold of consecutive failures is reached.
 *
 * States:
 *   CLOSED   — normal operation, requests pass through
 *   OPEN     — service is failing, requests are blocked immediately
 *   HALF_OPEN — after timeout, allow one probe request to test recovery
 *
 * Usage:
 *   $cb = new CircuitBreaker('stripe', failureThreshold: 5, recoveryTimeout: 60);
 *   try {
 *       $result = $cb->call(fn() => $stripe->charge($params));
 *   } catch (\App\Breakers\CircuitOpenException $e) {
 *       // Service is down, return cached/fallback response
 *   }
 */
class CircuitBreaker
{
    public const STATE_CLOSED   = 'closed';
    public const STATE_OPEN     = 'open';
    public const STATE_HALF_OPEN = 'half_open';

    protected string $name;
    protected string $state = self::STATE_CLOSED;
    protected int $failureCount = 0;
    protected int $successCount = 0;
    protected int $lastFailureAt = 0;
    protected int $failureThreshold;
    protected int $recoveryTimeout;
    protected int $successThreshold;

    public function __construct(
        string $name,
        int $failureThreshold = 5,
        int $recoveryTimeout = 60,
        int $successThreshold = 3
    ) {
        $this->name             = $name;
        $this->failureThreshold = $failureThreshold;
        $this->recoveryTimeout  = $recoveryTimeout;
        $this->successThreshold = $successThreshold;
    }

    /**
     * Execute a callable through the circuit breaker.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     * @throws CircuitOpenException if the circuit is open
     */
    public function call(callable $callback): mixed
    {
        $this->assertAllowed();

        try {
            $result = $callback();
            $this->onSuccess();
            return $result;
        } catch (\Throwable $e) {
            $this->onFailure();
            throw $e;
        }
    }

    /**
     * Manually mark the circuit as open (e.g. after detecting an external outage).
     */
    public function open(): void
    {
        $this->state      = self::STATE_OPEN;
        $this->lastFailureAt = time();
    }

    /**
     * Manually reset the circuit to closed.
     */
    public function close(): void
    {
        $this->state         = self::STATE_CLOSED;
        $this->failureCount  = 0;
        $this->successCount  = 0;
    }

    public function getState(): string
    {
        // Auto-transition from OPEN to HALF_OPEN after recovery timeout
        if ($this->state === self::STATE_OPEN && time() - $this->lastFailureAt >= $this->recoveryTimeout) {
            $this->state = self::STATE_HALF_OPEN;
        }
        return $this->state;
    }

    public function getFailureCount(): int
    {
        return $this->failureCount;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get breaker status as an array (for monitoring/health endpoints).
     */
    public function status(): array
    {
        return [
            'name'             => $this->name,
            'state'            => $this->getState(),
            'failure_count'    => $this->failureCount,
            'success_count'    => $this->successCount,
            'failure_threshold'=> $this->failureThreshold,
            'recovery_timeout' => $this->recoveryTimeout,
            'last_failure_at'  => $this->lastFailureAt > 0 ? date('c', $this->lastFailureAt) : null,
        ];
    }

    /**
     * Check if a request is allowed to pass through.
     *
     * @throws CircuitOpenException
     */
    protected function assertAllowed(): void
    {
        $state = $this->getState();

        if ($state === self::STATE_OPEN) {
            throw new CircuitOpenException(
                "Circuit breaker '{$this->name}' is OPEN. Service unavailable.",
                $this
            );
        }

        if ($state === self::STATE_HALF_OPEN && $this->successCount > 0) {
            // Already tested and failed in half-open; keep it open
            throw new CircuitOpenException(
                "Circuit breaker '{$this->name}' is still recovering.",
                $this
            );
        }
    }

    protected function onSuccess(): void
    {
        if ($this->state === self::STATE_HALF_OPEN) {
            $this->successCount++;
            if ($this->successCount >= $this->successThreshold) {
                $this->close();
            }
        } else {
            $this->failureCount = max(0, $this->failureCount - 1);
        }
    }

    protected function onFailure(): void
    {
        $this->failureCount++;
        $this->lastFailureAt = time();

        if ($this->failureCount >= $this->failureThreshold) {
            $this->state = self::STATE_OPEN;
        }
    }
}

/**
 * Exception thrown when the circuit breaker is open.
 */
class CircuitOpenException extends \RuntimeException
{
    public function __construct(string $message, public readonly CircuitBreaker $breaker)
    {
        parent::__construct($message);
    }
}
