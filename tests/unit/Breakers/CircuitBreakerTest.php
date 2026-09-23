<?php
declare(strict_types=1);

namespace Tests\Unit\Breakers;

use PHPUnit\Framework\TestCase;
use App\Breakers\CircuitBreaker;
use App\Breakers\CircuitOpenException;

class CircuitBreakerTest extends TestCase
{
    public function test_closed_allows_requests(): void
    {
        $cb = new CircuitBreaker('test-service', failureThreshold: 3);
        $this->assertSame(CircuitBreaker::STATE_CLOSED, $cb->getState());

        $result = $cb->call(fn() => 'ok');
        $this->assertSame('ok', $result);
    }

    public function test_opens_after_failure_threshold(): void
    {
        $cb = new CircuitBreaker('test-service', failureThreshold: 3);

        for ($i = 0; $i < 3; $i++) {
            try {
                $cb->call(fn() => throw new \RuntimeException('fail'));
            } catch (\RuntimeException $e) {
                // expected
            }
        }
        $this->assertSame(CircuitBreaker::STATE_OPEN, $cb->getState());
    }

    public function test_open_circuit_throws_exception(): void
    {
        $cb = new CircuitBreaker('test-service', failureThreshold: 2);
        for ($i = 0; $i < 2; $i++) {
            try {
                $cb->call(fn() => throw new \RuntimeException('fail'));
            } catch (\RuntimeException $e) {
                // expected
            }
        }

        $this->expectException(CircuitOpenException::class);
        $cb->call(fn() => 'should not reach here');
    }

    public function test_half_open_allows_probe(): void
    {
        $cb = new CircuitBreaker('test-service', failureThreshold: 2, recoveryTimeout: 0, successThreshold: 1);
        for ($i = 0; $i < 2; $i++) {
            try {
                $cb->call(fn() => throw new \RuntimeException('fail'));
            } catch (\RuntimeException $e) {
                // expected
            }
        }
        // With recoveryTimeout=0, getState() auto-transitions to HALF_OPEN
        $this->assertSame(CircuitBreaker::STATE_HALF_OPEN, $cb->getState());

        // Probe succeeds → circuit closes
        $result = $cb->call(fn() => 'recovered');
        $this->assertSame('recovered', $result);
        $this->assertSame(CircuitBreaker::STATE_CLOSED, $cb->getState());
    }

    public function test_half_open_probe_failure_keeps_open(): void
    {
        $cb = new CircuitBreaker('test-service', failureThreshold: 2, recoveryTimeout: 0);
        for ($i = 0; $i < 2; $i++) {
            try {
                $cb->call(fn() => throw new \RuntimeException('fail'));
            } catch (\RuntimeException $e) {
                // expected
            }
        }

        $ref = new \ReflectionClass($cb);
        $prop = $ref->getProperty('lastFailureAt');
        $prop->setValue($cb, time() - 10);

        // Probe fails → stays open
        $this->expectException(\RuntimeException::class);
        $cb->call(fn() => throw new \RuntimeException('still failing'));
        $this->assertSame(CircuitBreaker::STATE_OPEN, $cb->getState());
    }

    public function test_manual_open_and_close(): void
    {
        $cb = new CircuitBreaker('test-service');
        $cb->open();
        $this->assertSame(CircuitBreaker::STATE_OPEN, $cb->getState());

        $cb->close();
        $this->assertSame(CircuitBreaker::STATE_CLOSED, $cb->getState());
    }

    public function test_status_array(): void
    {
        $cb = new CircuitBreaker('payments', failureThreshold: 5, recoveryTimeout: 30);
        $status = $cb->status();

        $this->assertSame('payments', $status['name']);
        $this->assertSame(CircuitBreaker::STATE_CLOSED, $status['state']);
        $this->assertSame(5, $status['failure_threshold']);
        $this->assertSame(30, $status['recovery_timeout']);
        $this->assertSame(0, $status['failure_count']);
    }

    public function test_success_reduces_failure_count(): void
    {
        $cb = new CircuitBreaker('test-service', failureThreshold: 5);
        try { $cb->call(fn() => throw new \RuntimeException('fail')); } catch (\RuntimeException $e) {}
        try { $cb->call(fn() => throw new \RuntimeException('fail')); } catch (\RuntimeException $e) {}
        $this->assertSame(2, $cb->getFailureCount());

        $cb->call(fn() => 'ok');
        $this->assertSame(1, $cb->getFailureCount());
    }
}
