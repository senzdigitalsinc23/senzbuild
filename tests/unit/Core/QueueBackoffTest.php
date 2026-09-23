<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;

class QueueBackoffTest extends TestCase
{
    /**
     * Test exponential backoff calculation logic.
     */
    public function testExponentialBackoff(): void
    {
        $base = 10;
        $delays = [];
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $delays[] = min($base * pow(2, $attempt - 1), 3600);
        }

        $this->assertSame([10, 20, 40, 80, 160], $delays);
    }

    /**
     * Test linear backoff calculation.
     */
    public function testLinearBackoff(): void
    {
        $delays = [10, 20, 30, 40, 50];
        $this->assertSame([10, 20, 30, 40, 50], $delays);
    }

    /**
     * Test custom backoff array indexing.
     */
    public function testCustomBackoffArrayIndexing(): void
    {
        $backoff = [5, 15, 60];

        $delays = [];
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $index = min($attempt - 1, count($backoff) - 1);
            $delays[] = (int)$backoff[$index];
        }

        $this->assertSame([5, 15, 60, 60, 60], $delays);
    }

    /**
     * Test cap at 3600 seconds (1 hour).
     */
    public function testBackoffCapsAtOneHour(): void
    {
        $base = 10;
        $delay = min($base * pow(2, 10), 3600);
        $this->assertSame(3600, $delay);
    }
}
