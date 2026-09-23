<?php
declare(strict_types=1);

namespace App\Core;

/**
 * OpenTelemetry Bridge — exports framework Trace data to real OTel SDK or OTLP endpoint.
 *
 * Bridges the built-in Trace class with the official OpenTelemetry PHP SDK.
 * When the SDK is available, spans are exported via OTLP gRPC/HTTP.
 * When not available, falls back to the built-in in-memory Trace.
 *
 * Usage:
 *   // In bootstrap:
 *   if (class_exists(\OpenTelemetry\SDK\SDK::class)) {
 *       OtelBridge::init(['endpoint' => 'http://collector:4317']);
 *   }
 *
 *   // Then use Trace as normal — spans auto-export to OTel when available.
 */
class OtelBridge
{
    protected static bool $initialized = false;
    protected static ?object $tracerProvider = null;
    protected static ?object $tracer = null;
    protected static string $endpoint = '';
    protected static string $service = 'php-framework';

    /**
     * Initialize the OTel bridge with SDK configuration.
     *
     * @param array<string, mixed> $config
     *   - endpoint: OTLP collector URL (e.g. http://localhost:4317)
     *   - service: service name for traces
     *   - headers: additional headers for OTLP export
     */
    public static function init(array $config = []): void
    {
        // Check for OTel SDK
        if (!class_exists('\OpenTelemetry\SDK\TracerProviderBuilder')) {
            // SDK not available — stay in fallback mode
            self::$initialized = false;
            return;
        }

        self::$endpoint = $config['endpoint'] ?? $_ENV['OTEL_EXPORTER_OTLP_ENDPOINT'] ?? '';
        self::$service = $config['service'] ?? $_ENV['OTEL_SERVICE_NAME'] ?? 'php-framework';

        try {
            // Build OTel tracer provider
            $builder = new \OpenTelemetry\SDK\TracerProviderBuilder();
            $builder = $builder->addSpanProcessor(
                new \OpenTelemetry\SDK\Trace\Exporter\SpanExporter(
                    new \OpenTelemetry\Contrib\Otlp\GrpcExporter(
                        self::$endpoint,
                        \OpenTelemetry\Contrib\Otlp\Converter\Common::convertHeaders($config['headers'] ?? [])
                    )
                )
            );
            self::$tracerProvider = $builder->build();
            self::$tracer = self::$tracerProvider->getTracer(self::$service);
            self::$initialized = true;
        } catch (\Throwable $e) {
            // Fail gracefully — fall back to in-memory tracing
            self::$initialized = false;
        }
    }

    /**
     * Check if real OTel SDK is active.
     */
    public static function isSdkActive(): bool
    {
        return self::$initialized && self::$tracer !== null;
    }

    /**
     * Start a real OTel span, falling back to in-memory Trace.
     */
    public static function startSpan(string $name, array $attributes = []): ?object
    {
        if (self::$tracer === null) {
            // Fallback to in-memory trace
            Trace::startSpan($name, $attributes);
            return null;
        }

        $span = self::$tracer->span($name)
            ->setAttributes($attributes)
            ->startSpan();

        // Store span ID for context propagation
        Trace::startSpan($name, $attributes);

        return $span;
    }

    /**
     * End an OTel span.
     */
    public static function endSpan(?object $span, int $statusCode = 1): void
    {
        if ($span !== null) {
            $span->setStatus($statusCode);
            $span->end();
        }
        Trace::endSpan();
    }

    /**
     * Export all collected spans to OTLP endpoint.
     */
    public static function flush(): void
    {
        if (self::$tracerProvider !== null && method_exists(self::$tracerProvider, 'forceFlush')) {
            try {
                self::$tracerProvider->forceFlush();
            } catch (\Throwable $e) {
                // Export failed — spans will be retried on next flush
            }
        }
    }

    /**
     * Get the current OTel trace ID (for propagation headers).
     */
    public static function getTraceContext(): ?array
    {
        if (!self::$initialized || self::$tracer === null) {
            return null;
        }

        $context = \OpenTelemetry\Context\Context::getCurrent();
        $spanContext = \OpenTelemetry\Trace\SpanContext::fromArray([
            'traceId' => Trace::getTraceId() ?? bin2hex(random_bytes(16)),
            'spanId'  => bin2hex(random_bytes(8)),
            'traceFlags' => 1,
            'traceState' => null,
        ]);

        return [
            'traceparent' => sprintf(
                '00-%s-%s-01',
                $spanContext->getTraceId(),
                $spanContext->getSpanId()
            ),
        ];
    }

    /**
     * Inject trace context into an HTTP request (for outgoing calls).
     */
    public static function inject(array &$headers): void
    {
        $ctx = self::getTraceContext();
        if ($ctx !== null) {
            foreach ($ctx as $key => $value) {
                $headers[$key] = $value;
            }
        }
    }

    /**
     * Reset bridge state.
     */
    public static function reset(): void
    {
        self::$initialized = false;
        self::$tracerProvider = null;
        self::$tracer = null;
        Trace::reset();
    }
}
