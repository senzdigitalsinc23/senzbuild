<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\Metrics;

class MetricsTest extends TestCase
{
    protected function tearDown(): void
    {
        Metrics::reset();
    }

    public function test_counter_increments(): void
    {
        Metrics::increment('http.requests');
        Metrics::increment('http.requests');
        Metrics::increment('http.requests');
        $rendered = Metrics::render();
        $this->assertStringContainsString('http.requests 3', $rendered);
    }

    public function test_gauge(): void
    {
        Metrics::gauge('db.connections.active', 5);
        $rendered = Metrics::render();
        $this->assertStringContainsString('db.connections.active 5', $rendered);
    }

    public function test_histogram(): void
    {
        Metrics::histogram('request.duration', 0.1);
        Metrics::histogram('request.duration', 0.3);
        $rendered = Metrics::render();
        $this->assertStringContainsString('request.duration_sum 0.4', $rendered);
        $this->assertStringContainsString('request.duration_count 2', $rendered);
    }

    public function test_labels(): void
    {
        Metrics::increment('api.calls', 1, ['method' => 'GET']);
        $rendered = Metrics::render();
        $this->assertStringContainsString('api.calls{method="GET"} 1', $rendered);
    }
}
