<?php
declare(strict_types=1);
namespace App\CLI;

abstract class Command implements CommandInterface
{
    protected bool $verbose = false;

    public function __construct(array $args = [])
    {
        if (in_array('--verbose', $args, true)) {
            $this->verbose = true;
        }
    }

    protected function logVerbose(string $message): void
    {
        if ($this->verbose) {
            echo "\033[90m[VERBOSE]\033[0m {$message}\n"; // Gray
        }
    }

    protected function success(string $message): void
    {
        echo "\033[42;97m SUCCESS \033[0m {$message}\n"; // White text on green background
    }

    protected function error(string $message): void
    {
        echo "\033[41;97m ERROR \033[0m {$message}\n"; // White text on red background
    }

    protected function warning(string $message): void
    {
        echo "\033[43;30m WARNING \033[0m {$message}\n"; // Black text on yellow background
    }

    protected function info(string $message): void
    {
        echo "\033[44;97m INFO \033[0m {$message}\n"; // White text on blue background
    }
}
