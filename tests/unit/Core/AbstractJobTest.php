<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\AbstractJob;
use App\Core\Queue;

class AbstractJobTest extends TestCase
{
    public function testDefaultMaxTries(): void
    {
        $job = new class extends AbstractJob {
            public function handle(): void {}
        };

        $this->assertSame(3, $job->maxTries());
    }

    public function testDefaultBackoff(): void
    {
        $job = new class extends AbstractJob {
            public function handle(): void {}
        };

        $this->assertSame('exponential', $job->backoff());
    }

    public function testCustomMaxTries(): void
    {
        $job = new class extends AbstractJob {
            public function handle(): void {}
        };

        $job->setMaxTries(7);
        $this->assertSame(7, $job->maxTries());
    }

    public function testCustomBackoffArray(): void
    {
        $job = new class extends AbstractJob {
            public function handle(): void {}
        };

        $job->setBackoff([1, 5, 30]);
        $this->assertSame([1, 5, 30], $job->backoff());
    }

    public function testImplementsInterfaces(): void
    {
        $job = new class extends AbstractJob {
            public function handle(): void {}
        };

        $this->assertInstanceOf(\App\Core\Job::class, $job);
        $this->assertInstanceOf(\App\Core\ShouldQueue::class, $job);
    }
}
