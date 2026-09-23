<?php
declare(strict_types=1);

namespace App\Core;

/**
 * OpenTelemetry — trace context propagation and span creation.
 *
 * Provides a lightweight tracing layer compatible with OTel SDK.
 *
 * Usage:
 *   Trace::startSpan('http.request', ['request' => $request]);
 *   // ... business logic ...
 *   Trace::endSpan();
 *
 *   // Or as decorator:
 *   Trace::span('db.query', fn() => $repo->findAll());
 */
class Trace
{
    protected static array $spans = [];
    protected static array $contexts = [];
    protected static bool $enabled = false;
    protected static string $service = 'php-framework';
    protected static float $startTime;
    protected static ?string $currentTraceId = null;

    /**
     * Enable tracing.
     */
    public static function enable(): void
    {
        self::$enabled = true;
    }

    /**
     * Disable tracing.
     */
    public static function disable(): void
    {
        self::$enabled = false;
    }

    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    /**
     * Set the service name (appears in traces).
     */
    public static function setService(string $name): void
    {
        self::$service = $name;
    }

    /**
     * Start a new span.
     *
     * @param string $name Span name
     * @param array $attributes Span attributes
     * @param string|null $parentId Parent span ID (for nested spans)
     * @return string Span ID
     */
    public static function startSpan(string $name, array $attributes = [], ?string $parentId = null): string
    {
        if (!self::$enabled) {
            return '';
        }

        $spanId = bin2hex(random_bytes(8));
        if (self::$currentTraceId === null) {
            self::$currentTraceId = bin2hex(random_bytes(16));
        }
        $traceId = self::$currentTraceId;

        $span = [
            'id'         => $spanId,
            'trace_id'   => $traceId,
            'parent_id'  => $parentId,
            'name'       => $name,
            'service'    => self::$service,
            'start'      => microtime(true),
            'end'        => null,
            'attributes' => $attributes,
            'status'     => 'ok',
        ];

        self::$spans[$spanId] = $span;
        self::$contexts[] = $spanId;

        return $spanId;
    }

    /**
     * End the current span and record duration.
     */
    public static function endSpan(?string $spanId = null, array $attributes = []): void
    {
        if (!self::$enabled || empty(self::$contexts)) {
            return;
        }

        $spanId = $spanId ?? array_pop(self::$contexts);
        if ($spanId === null || !isset(self::$spans[$spanId])) {
            return;
        }

        self::$spans[$spanId]['end'] = microtime(true);
        foreach ($attributes as $k => $v) {
            self::$spans[$spanId]['attributes'][$k] = $v;
        }
    }

    /**
     * Execute a callback within a span.
     *
     * @param string $name
     * @param callable $callback
     * @param array $attributes
     * @return mixed
     */
    public static function span(string $name, callable $callback, array $attributes = []): mixed
    {
        $spanId = self::startSpan($name, $attributes);
        try {
            return $callback();
        } catch (\Throwable $e) {
            if ($spanId !== '') {
                self::$spans[$spanId]['status'] = 'error';
                self::$spans[$spanId]['attributes']['error'] = $e->getMessage();
            }
            throw $e;
        } finally {
            self::endSpan($spanId);
        }
    }

    /**
     * Add an attribute to the current span.
     */
    public static function addAttribute(string $key, mixed $value): void
    {
        if (empty(self::$contexts)) {
            return;
        }
        $spanId = end(self::$contexts);
        if ($spanId !== false && isset(self::$spans[$spanId])) {
            self::$spans[$spanId]['attributes'][$key] = $value;
        }
    }

    /**
     * Get the current trace ID.
     */
    public static function getTraceId(): ?string
    {
        if (empty(self::$contexts)) {
            return null;
        }
        $spanId = end(self::$contexts);
        return $spanId !== false && isset(self::$spans[$spanId])
            ? self::$spans[$spanId]['trace_id']
            : null;
    }

    /**
     * Get all recorded spans.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getSpans(): array
    {
        return array_values(self::$spans);
    }

    /**
     * Get spans formatted as JSON for export (OTel-compatible).
     */
    public static function exportJson(): string
    {
        $spans = [];
        foreach (self::$spans as $span) {
            $spans[] = [
                'traceId'    => $span['trace_id'],
                'spanId'     => $span['id'],
                'parentSpanId' => $span['parent_id'] ?? null,
                'name'       => $span['name'],
                'kind'       => 'SPAN_KIND_INTERNAL',
                'startTimeUnixNano' => (int)($span['start'] * 1e9),
                'endTimeUnixNano'   => (int)(($span['end'] ?? microtime(true)) * 1e9),
                'attributes' => $span['attributes'],
                'status'     => [
                    'code' => $span['status'] === 'error' ? 2 : 1,
                ],
            ];
        }
        return json_encode(['resourceSpans' => [['spans' => $spans]]], JSON_PRETTY_PRINT);
    }

    /**
     * Reset all trace state.
     */
    public static function reset(): void
    {
        self::$spans = [];
        self::$contexts = [];
        self::$currentTraceId = null;
    }
}
