<?php
declare(strict_types=1);
namespace App\Core;

abstract class TestCase
{
    protected int $assertions = 0;
    protected int $failures = 0;

    public function assertTrue(bool $condition, string $message = ''): void
    {
        $this->assertions++;
        if (!$condition) {
            $this->failures++;
            echo "❌ Assertion failed: $message\n";
        }
    }

    public function assertEquals($expected, $actual, string $message = ''): void
    {
        $this->assertions++;
        if ($expected !== $actual) {
            $this->failures++;
            echo "❌ Assertion failed: $message. Expected: " . var_export($expected, true) . ", Got: " . var_export($actual, true) . "\n";
        }
    }

    public function run(): void
    {
        $methods = get_class_methods($this);
        foreach ($methods as $method) {
            if (str_starts_with($method, 'test')) {
                $this->$method();
            }
        }

        echo "\nTests run: {$this->assertions}, Failures: {$this->failures}\n";
    }
}
