<?php
declare(strict_types=1);

namespace App\Core;

/**
 * N+1 Query Detector — tracks repeated queries in debug mode.
 *
 * Usage:
 *   // Enable in config or bootstrap:
 *   QueryDetector::enable();
 *
 *   // After dispatch:
 *   $warnings = QueryDetector::getWarnings();
 *   QueryDetector::reset();
 */
class QueryDetector
{
    protected static bool $enabled = false;
    protected static array $queries = [];
    protected static array $warnings = [];
    protected static int $maxQueriesPerContext = 10;

    /**
     * Enable the detector.
     */
    public static function enable(): void
    {
        self::$enabled = true;
    }

    /**
     * Disable the detector.
     */
    public static function disable(): void
    {
        self::$enabled = false;
    }

    /**
     * Check if the detector is enabled.
     */
    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    /**
     * Record a query execution.
     *
     * @param string $query The SQL query
     * @param array<string, mixed> $params Query parameters
     * @param string $context Call context (class::method or file:line)
     */
    public static function record(string $query, array $params = [], string $context = ''): void
    {
        if (!self::$enabled) {
            return;
        }

        $key = md5($query);
        self::$queries[$key][] = [
            'query' => $query,
            'params' => $params,
            'context' => $context,
            'time' => microtime(true),
        ];
    }

    /**
     * Check for N+1 patterns and generate warnings.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getWarnings(): array
    {
        self::$warnings = [];

        foreach (self::$queries as $key => $calls) {
            if (count($calls) > self::$maxQueriesPerContext) {
                $first = $calls[0];
                self::$warnings[] = [
                    'query' => $first['query'],
                    'count' => count($calls),
                    'contexts' => array_unique(array_column($calls, 'context')),
                ];
            }
        }

        return self::$warnings;
    }

    /**
     * Get the total number of recorded queries.
     *
     * @return int
     */
    public static function getTotalQueries(): int
    {
        $total = 0;
        foreach (self::$queries as $calls) {
            $total += count($calls);
        }
        return $total;
    }

    /**
     * Reset all tracking data.
     */
    public static function reset(): void
    {
        self::$queries = [];
        self::$warnings = [];
    }

    /**
     * Set the max queries per context threshold.
     *
     * @param int $max
     */
    public static function setMaxQueries(int $max): void
    {
        self::$maxQueriesPerContext = $max;
    }

    /**
     * Get all recorded queries grouped by hash.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function getQueries(): array
    {
        return self::$queries;
    }
}
