<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Event Bus — publish-subscribe pattern with event routing and replay.
 *
 * Extends PSR-14 EventDispatcher with event routing, wildcards, and replay.
 *
 * Usage:
 *   EventBus::subscribe('order.*', function($event) { ... });
 *   EventBus::subscribe('order.created', fn($e) => notify($e->order));
 *   EventBus::publish(new OrderCreated($order));
 *   EventBus::replay('order.*', $sinceTimestamp);
 */
class EventBus extends EventDispatcher
{
    /** @var array<string, callable[]> Wildcard route listeners */
    protected array $wildcardListeners = [];

    /** @var array<int, object> Event log for replay */
    protected static array $eventLog = [];

    protected static int $logMaxSize = 10000;

    /**
     * Subscribe with wildcard patterns.
     *
     * Patterns:
     *   'order.created'    — exact match
     *   'order.*'          — one-level wildcard
     *   'order.**'         — multi-level wildcard (matches order.anything.here)
     *   '*.created'        — prefix wildcard
     */
    public function subscribe(string $pattern, callable $listener): void
    {
        if (str_contains($pattern, '*') || str_contains($pattern, '**')) {
            $this->wildcardListeners[$pattern][] = $listener;
        } else {
            parent::listen($pattern, $listener);
            // Also register under short name for convenience
            if (class_exists($pattern)) {
                $shortName = (new \ReflectionClass($pattern))->getShortName();
                if ($shortName !== $pattern) {
                    $this->listeners[$shortName][] = $listener;
                }
            }
        }
    }

    /**
     * Publish an event (routes to both exact and wildcard listeners).
     */
    public function publish(object $event): object
    {
        // Log event
        self::$eventLog[] = $event;
        if (count(self::$eventLog) > self::$logMaxSize) {
            array_shift(self::$eventLog);
        }

        // Dispatch via parent (exact matches by full class name)
        $event = parent::dispatch($event);

        // Route to wildcard listeners using short class name
        $shortName = (new \ReflectionClass($event))->getShortName();
        foreach ($this->wildcardListeners as $pattern => $listeners) {
            if ($this->matches($pattern, $shortName)) {
                foreach ($listeners as $listener) {
                    $listener($event);
                }
            }
        }

        return $event;
    }

    /**
     * Replay events matching a pattern from a timestamp.
     *
     * @param string $pattern Event pattern (e.g., 'OrderCreated')
     * @param int $since Unix timestamp
     * @return array<int, object> Matching events
     */
    public static function replay(string $pattern, int $since = 0): array
    {
        $results = [];
        foreach (self::$eventLog as $event) {
            $className = $event::class;
            $parts = explode('\\', $className);
            $eventName = end($parts);
            if (self::patternMatches($pattern, $eventName)) {
                $results[] = $event;
            }
        }
        return $results;
    }

    /**
     * Check if a pattern matches an event name.
     */
    protected function matches(string $pattern, string $eventName): bool
    {
        // Quote special regex chars first, THEN handle wildcards
        $quoted = preg_quote($pattern, '/');
        // Convert escaped wildcards back to regex
        $quoted = str_replace('\*\*', '.*', $quoted);  // multi-level wildcard
        $quoted = str_replace('\*', '[^.]+', $quoted);  // single-level wildcard
        $quoted = str_replace('\.', '.', $quoted);       // dots match any char
        $regex = '/^' . $quoted . '$/';
        return (bool)preg_match($regex, $eventName);
    }

    /**
     * Static pattern matcher for replay.
     */
    protected static function patternMatches(string $pattern, string $eventName): bool
    {
        if ($pattern === $eventName) {
            return true;
        }
        $quoted = preg_quote($pattern, '/');
        $quoted = str_replace('\*\*', '.*', $quoted);
        $quoted = str_replace('\*', '[^.]+', $quoted);
        $quoted = str_replace('\.', '.', $quoted);
        $regex = '/^' . $quoted . '$/';
        return (bool)preg_match($regex, $eventName);
    }

    /**
     * Clear the event log.
     */
    public static function clearLog(): void
    {
        self::$eventLog = [];
    }

    /**
     * Get the total number of logged events.
     */
    public static function logSize(): int
    {
        return count(self::$eventLog);
    }

    /**
     * Set max log size.
     */
    public static function setMaxLogSize(int $size): void
    {
        self::$logMaxSize = $size;
    }
}
