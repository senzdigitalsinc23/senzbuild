<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Tinker REPL — interactive shell for exploring the application.
 *
 * Usage (CLI):
 *   php bin/console tinker
 *
 * Inside tinker:
 *   $user = User::find(1);
 *   $user->name = 'New Name';
 *   $user->save();
 *   DB::table('users')->count();
 */
class Tinker
{
    protected static array $aliases = [];
    protected static bool $running = false;

    /**
     * Register a global alias/helper.
     */
    public static function alias(string $name, $value): void
    {
        self::$aliases[$name] = $value;
    }

    /**
     * Run the Tinker REPL interactively.
     */
    public static function run(): void
    {
        self::$running = true;
        echo "PHP Framework Tinker\n";
        echo "Type 'help' for available commands, 'exit' to quit\n\n";

        $historyFile = dirname(__DIR__) . '/storage/tinker_history';
        if (function_exists('readline')) {
            readline_add_history($historyFile);
        }

        while (self::$running) {
            $prompt = "\033[32m>>\033[0m ";
            $line = self::readLine($prompt);

            if ($line === null || trim($line) === '') {
                continue;
            }

            // Commands
            if (trim($line) === 'exit' || trim($line) === 'quit') {
                self::$running = false;
                break;
            }

            if (trim($line) === 'help') {
                self::showHelp();
                continue;
            }

            if (trim($line) === 'clear') {
                echo "\033[H\033[J";
                continue;
            }

            if (str_starts_with(trim($line), ':')) {
                self::handleCommand(substr($line, 1));
                continue;
            }

            // Evaluate PHP code
            try {
                $result = eval('return ' . $line . ';');
                if ($result !== null) {
                    echo static::varDump($result) . "\n";
                }
            } catch (\Throwable $e) {
                echo "\033[31mError: {$e->getMessage()}\033[0m\n";
            }

            if (function_exists('readline_add_history')) {
                readline_add_history($line);
            }
        }
    }

    /**
     * Show help information.
     */
    protected static function showHelp(): void
    {
        echo "Available commands:\n";
        echo "  :dump <expr>    Dump a variable\n";
        echo "  :classes        List loaded classes\n";
        echo "  :config         Show config keys\n";
        echo "  :env <key>      Get env value\n";
        echo "  :aliases        List registered aliases\n";
        echo "  :exit           Exit tinker\n";
        echo "  :help           Show this help\n\n";
        echo "You can also execute any PHP code directly:\n";
        echo "  \$user = User::find(1);\n";
        echo "  DB::table('users')->count();\n";
    }

    /**
     * Handle a command.
     */
    protected static function handleCommand(string $cmd): void
    {
        $parts = explode(' ', trim($cmd), 2);
        $command = $parts[0];
        $arg = $parts[1] ?? null;

        switch ($command) {
            case 'dump':
                try {
                    echo static::varDump(eval('return ' . $arg . ';'));
                } catch (\Throwable $e) {
                    echo "Error: " . $e->getMessage() . "\n";
                }
                break;
            case 'classes':
                foreach (get_declared_classes() as $class) {
                    if (str_starts_with($class, 'App\\') || str_starts_with($class, 'Database\\')) {
                        echo "  {$class}\n";
                    }
                }
                break;
            case 'config':
                $ref = new \ReflectionClass(\App\Core\Config::class);
                $prop = $ref->getProperty('config');
                $prop->setAccessible(true);
                $config = $prop->getValue();
                foreach (array_keys($config) as $key) {
                    echo "  {$key}\n";
                }
                break;
            case 'env':
                echo env($arg) . "\n";
                break;
            case 'aliases':
                foreach (self::$aliases as $name => $val) {
                    echo "  {$name} => " . (is_object($val) ? get_class($val) : var_export($val, true)) . "\n";
                }
                break;
            default:
                echo "Unknown command: {$command}\n";
        }
    }

    /**
     * Read a line of input (with readline support if available).
     */
    protected static function readLine(string $prompt): ?string
    {
        if (function_exists('readline')) {
            return readline($prompt);
        }
        echo $prompt;
        return fgets(STDIN);
    }

    /**
     * Pretty-print a value.
     */
    protected static function varDump(mixed $value): string
    {
        if (is_object($value)) {
            return get_class($value);
        }
        if (is_array($value)) {
            return print_r($value, true);
        }
        return var_export($value, true);
    }
}
