<?php
declare(strict_types=1);

namespace App\Core\Log;

class LogLevel
{
    public const DEBUG    = 'DEBUG';
    public const INFO     = 'INFO';
    public const WARNING  = 'WARNING';
    public const ERROR    = 'ERROR';
    public const CRITICAL = 'CRITICAL';

    public static function getPriority(string $level): int
    {
        return match($level) {
            self::DEBUG    => 0,
            self::INFO     => 1,
            self::WARNING  => 2,
            self::ERROR    => 3,
            self::CRITICAL => 4,
            default        => 1,
        };
    }
}
