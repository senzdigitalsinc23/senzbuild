<?php
declare(strict_types=1);

namespace App\Core\Log;

use Exception;

class Logger
{
    private string $logPath;
    private string $minLevel;

    public function __construct(string $logPath = 'storage/logs/app.log', string $minLevel = LogLevel::INFO)
    {
        $this->logPath = $logPath;
        $this->minLevel = $minLevel;
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function log(string $level, string $message, array $context = []): void
    {
        if (LogLevel::getPriority($level) < LogLevel::getPriority($this->minLevel)) {
            return;
        }

        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level'     => $level,
            'message'   => $message,
            'context'   => $context,
            'request_id'=> $_SERVER['REQUEST_ID'] ?? 'N/A',
            'uri'       => $_SERVER['REQUEST_URI'] ?? 'CLI'
        ];

        $formattedMessage = json_encode($logEntry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

        try {
            file_put_contents($this->logPath, $formattedMessage, FILE_APPEND);
        } catch (Exception $e) {
            // Fail silently to avoid crashing the app due to logging failures
            error_log("Logger failed: " . $e->getMessage());
        }
    }
}
