<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Syslog log channel — sends logs to system syslog (Linux/macOS).
 *
 * Usage:
 *   Logger::channel('syslog', [
 *       'facility' => LOG_USER,
 *       'ident'    => 'myapp',
 *   ]);
 */
class SyslogLogger implements \Psr\Log\LoggerInterface
{
    protected int $facility;
    protected string $ident;

    public function __construct(int $facility = LOG_USER, string $ident = '')
    {
        $this->facility = $facility;
        $this->ident = $ident;
    }

    public function emergency($message, array $context = []): void
    {
        $this->log(\Psr\Log\LogLevel::EMERGENCY, $message, $context);
    }

    public function alert($message, array $context = []): void
    {
        $this->log(\Psr\Log\LogLevel::ALERT, $message, $context);
    }

    public function critical($message, array $context = []): void
    {
        $this->log(\Psr\Log\LogLevel::CRITICAL, $message, $context);
    }

    public function error($message, array $context = []): void
    {
        $this->log(\Psr\Log\LogLevel::ERROR, $message, $context);
    }

    public function warning($message, array $context = []): void
    {
        $this->log(\Psr\Log\LogLevel::WARNING, $message, $context);
    }

    public function notice($message, array $context = []): void
    {
        $this->log(\Psr\Log\LogLevel::NOTICE, $message, $context);
    }

    public function info($message, array $context = []): void
    {
        $this->log(\Psr\Log\LogLevel::INFO, $message, $context);
    }

    public function debug($message, array $context = []): void
    {
        $this->log(\Psr\Log\LogLevel::DEBUG, $message, $context);
    }

    /**
     * @param mixed $level
     */
    public function log($level, $message, array $context = []): void
    {
        if (!function_exists('syslog')) {
            return;
        }

        $formatted = $this->format($level, $message, $context);
        syslog($this->facility, $this->ident . ': ' . $formatted);
    }

    protected function format(string $level, string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            $replace['{' . $key . '}'] = is_scalar($val) || $val === null ? (string)$val : json_encode($val);
        }
        return strtr($message, $replace) . ' [' . $level . ']';
    }
}
