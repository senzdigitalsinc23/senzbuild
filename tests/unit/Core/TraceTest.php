<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\Trace;

class TraceTest extends TestCase
{
    protected function tearDown(): void
    {
        Trace::reset();
        Trace::disable();
    }

    public function test_span_creation(): void
    {
        Trace::enable();
        $spanId = Trace::startSpan('test.span', ['key' => 'value']);
        $this->assertNotEmpty($spanId);
        Trace::endSpan($spanId);

        $spans = Trace::getSpans();
        $this->assertCount(1, $spans);
        $this->assertSame('test.span', $spans[0]['name']);
    }

    public function test_span_decorator(): void
    {
        Trace::enable();
        $result = Trace::span('outer', function () {
            return Trace::span('inner', function () {
                return 42;
            });
        });
        $this->assertSame(42, $result);
        $this->assertCount(2, Trace::getSpans());
    }

    public function test_trace_id_propagation(): void
    {
        Trace::enable();
        Trace::startSpan('a');
        $traceId = Trace::getTraceId();
        $this->assertNotNull($traceId);
        Trace::startSpan('b');
        $this->assertSame($traceId, Trace::getTraceId());
    }

    public function test_error_span(): void
    {
        Trace::enable();
        $this->expectException(\RuntimeException::class);
        Trace::span('failing', function () {
            throw new \RuntimeException('boom');
        });
    }

    public function test_export_json(): void
    {
        Trace::enable();
        Trace::startSpan('http.request');
        Trace::endSpan();
        $json = Trace::exportJson();
        $data = json_decode($json, true);
        $this->assertArrayHasKey('resourceSpans', $data);
        $this->assertCount(1, $data['resourceSpans'][0]['spans']);
    }
}
