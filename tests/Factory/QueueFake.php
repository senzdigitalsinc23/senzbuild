<?php
declare(strict_types=1);

namespace Tests\Factory;

class QueueFake
{
    protected static array $jobs = [];

    public static function push($job): void
    {
        self::$jobs[] = $job;
    }

    public static function fake(): void
    {
        self::$jobs = [];
    }

    public static function assertPushed($job = null, callable $callback = null): void
    {
        if ($job === null) {
            return;
        }
        $found = false;
        foreach (self::$jobs as $queued) {
            if ($queued === $job || ($callback && $callback($queued))) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            throw new \Exception("Expected job to be pushed but it was not.");
        }
    }

    public static function assertPushedOn($queue, $job = null): void
    {
        self::assertPushed($job);
    }

    public static function assertNothingPushed(): void
    {
        if (!empty(self::$jobs)) {
            throw new \Exception("Expected no jobs to be pushed but got " . count(self::$jobs) . ".");
        }
    }

    public static function pop($queue = null)
    {
        return array_shift(self::$jobs);
    }

    public static function clear(): void
    {
        self::$jobs = [];
    }
}
