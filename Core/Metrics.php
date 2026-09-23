<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Prometheus Metrics — expose application metrics at /metrics.
 *
 * Usage:
 *   Metrics::increment('http.requests');
 *   Metrics::gauge('db.connections.active', 5);
 *   Metrics::histogram('http.request.duration', 0.25, ['method' => 'GET']);
 *
 * In controller:
 *   return new Response(Metrics::render(), 200, ['Content-Type' => 'text/plain']);
 */
class Metrics
{
    protected static array $counters   = [];
    protected static array $gauges     = [];
    protected static array $histograms = [];
    protected static array $summaries = [];

    /**
     * Increment a counter metric.
     *
     * @param string $name Metric name (dots replaced with underscores)
     * @param float $by Amount to increment
     * @param array $labels Label key-value pairs
     */
    public static function increment(string $name, float $by = 1, array $labels = []): void
    {
        $key = self::key($name, $labels);
        self::$counters[$key] = (self::$counters[$key] ?? 0) + $by;
    }

    /**
     * Set a gauge metric to an absolute value.
     */
    public static function gauge(string $name, float $value, array $labels = []): void
    {
        $key = self::key($name, $labels);
        self::$gauges[$key] = $value;
    }

    /**
     * Record a histogram observation.
     */
    public static function histogram(string $name, float $value, array $labels = []): void
    {
        $key = self::key($name, $labels);
        if (!isset(self::$histograms[$key])) {
            self::$histograms[$key] = ['sum' => 0, 'count' => 0, 'buckets' => []];
        }
        self::$histograms[$key]['sum'] += $value;
        self::$histograms[$key]['count']++;
    }

    /**
     * Observe a duration in seconds.
     */
    public static function observe(string $name, float $duration, array $labels = []): void
    {
        self::histogram($name, $duration, $labels);
    }

    /**
     * Render all metrics in Prometheus exposition format.
     */
    public static function render(): string
    {
        $lines = [];

        // Counters
        foreach (self::$counters as $key => $value) {
            $metricName = self::parseKey($key)['name'];
            $labels = self::parseKey($key)['labels'];
            $labelStr = self::labelsToString($labels);
            $lines[] = "{$metricName}{$labelStr} {$value}";
        }

        // Gauges
        foreach (self::$gauges as $key => $value) {
            $metricName = self::parseKey($key)['name'];
            $labels = self::parseKey($key)['labels'];
            $labelStr = self::labelsToString($labels);
            $lines[] = "{$metricName}{$labelStr} {$value}";
        }

        // Histograms
        foreach (self::$histograms as $key => $data) {
            $metricName = self::parseKey($key)['name'];
            $labels = self::parseKey($key)['labels'];
            $labelStr = self::labelsToString($labels);
            $avg = $data['count'] > 0 ? $data['sum'] / $data['count'] : 0;
            $lines[] = "{$metricName}_sum{$labelStr} {$data['sum']}";
            $lines[] = "{$metricName}_count{$labelStr} {$data['count']}";
            $lines[] = "{$metricName}_avg{$labelStr} {$avg}";
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Reset all metrics.
     */
    public static function reset(): void
    {
        self::$counters = [];
        self::$gauges = [];
        self::$histograms = [];
    }

    // ── Internal ─────────────────────────────────────────────────────────

    protected static function key(string $name, array $labels): string
    {
        ksort($labels);
        $sanitizedName = str_replace('.', '_', $name);
        return $sanitizedName . '::' . http_build_query($labels, '', ',');
    }

    protected static function parseKey(string $key): array
    {
        $parts = explode('::', $key, 2);
        $name = str_replace('_', '.', $parts[0]);
        $labels = [];
        if (isset($parts[1])) {
            parse_str(str_replace(',', '&', $parts[1]), $labels);
        }
        return ['name' => $name, 'labels' => $labels];
    }

    protected static function labelsToString(array $labels): string
    {
        if (empty($labels)) {
            return '';
        }
        $pairs = [];
        foreach ($labels as $k => $v) {
            $pairs[] = "{$k}=\"{$v}\"";
        }
        return '{' . implode(',', $pairs) . '}';
    }
}
