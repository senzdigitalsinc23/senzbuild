<?php
declare(strict_types=1);

namespace App\Core;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * PSR-3 compliant logger with structured logging and context support
 * 
 * Provides different log levels, structured logging with context data,
 * and automatic log rotation for better debugging and monitoring.
 */
class Logger implements LoggerInterface
{
    private string $logPath;
    private string $minLevel;
    private int $maxFileSize;
    private int $maxFiles;

    private array $levels = [
        LogLevel::DEBUG => 0,
        LogLevel::INFO => 1,
        LogLevel::NOTICE => 2,
        LogLevel::WARNING => 3,
        LogLevel::ERROR => 4,
        LogLevel::CRITICAL => 5,
        LogLevel::ALERT => 6,
        LogLevel::EMERGENCY => 7,
    ];

    private bool $useJsonFormat;

    /**
     * Constructor
     *
     * @param string|null $logPath Path to log file
     * @param string $minLevel Minimum log level
     * @param int $maxFileSize Maximum file size in bytes (default: 10MB)
     * @param int $maxFiles Maximum number of rotated files (default: 5)
     * @param bool $useJsonFormat Use JSON format for structured logging
     */
    public function __construct(
        ?string $logPath = null,
        string $minLevel = LogLevel::INFO,
        int $maxFileSize = 10485760, // 10MB
        int $maxFiles = 5,
        bool $useJsonFormat = false
    ) {
        $this->logPath = $logPath ?? $this->getDefaultLogPath();
        $this->minLevel = $minLevel;
        $this->maxFileSize = $maxFileSize;
        $this->maxFiles = $maxFiles;
        $this->useJsonFormat = $useJsonFormat || ($_ENV['LOG_FORMAT'] ?? '') === 'json';
        
        // Ensure log directory exists
        $this->ensureLogDirectory();
    }

    /**
     * Log a message with given level
     *
     * @param mixed $level Log level
     * @param string $message Log message
     * @param array $context Context data
     * @return void
     */
    public function log($level, $message, array $context = []): void
    {
        // Skip if below minimum level
        if ($this->levels[$level] < $this->levels[$this->minLevel]) {
            return;
        }

        // Rotate log if needed
        $this->rotateLogIfNeeded();

        // Format log entry
        $logEntry = $this->formatLogEntry($level, $message, $context);

        // Write to log file — suppress any filesystem warnings so they never corrupt JSON responses
        @error_log($logEntry, 3, $this->logPath);
    }

    /**
     * System is unusable
     */
    public function emergency($message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Action must be taken immediately
     */
    public function alert($message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * Critical conditions
     */
    public function critical($message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action
     */
    public function error($message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors
     */
    public function warning($message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Normal but significant events
     */
    public function notice($message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Interesting events
     */
    public function info($message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * Detailed debug information
     */
    public function debug($message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Log with request context (convenience method)
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context
     * @return void
     */
    public function logWithRequest(string $level, string $message, array $context = []): void
    {
        $requestContext = [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A',
        ];

        $this->log($level, $message, array_merge($context, $requestContext));
    }

    /**
     * Log performance metrics
     *
     * @param string $operation Operation name
     * @param float $duration Duration in milliseconds
     * @param array $context Additional context
     * @return void
     */
    public function logPerformance(string $operation, float $duration, array $context = []): void
    {
        $performanceContext = array_merge($context, [
            'operation' => $operation,
            'duration_ms' => round($duration, 2),
            'memory_usage' => $this->formatBytes(memory_get_usage()),
            'memory_peak' => $this->formatBytes(memory_get_peak_usage()),
        ]);

        $level = $duration > 1000 ? LogLevel::WARNING : LogLevel::INFO;
        $this->log($level, "Performance: {$operation}", $performanceContext);
    }

    /**
     * Log security events
     *
     * @param string $event Security event type
     * @param string $message Event message
     * @param array $context Additional context
     * @return void
     */
    public function logSecurity(string $event, string $message, array $context = []): void
    {
        $securityContext = array_merge($context, [
            'event_type' => $event,
            'timestamp' => time(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A',
        ]);

        $this->log(LogLevel::WARNING, "SECURITY: {$message}", $securityContext);
    }

    /**
     * Format log entry
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Context data
     * @return string Formatted log entry
     */
    private function formatLogEntry(string $level, string $message, array $context): string
    {
        if ($this->useJsonFormat) {
            $entry = [
                'timestamp' => date('Y-m-d\TH:i:s.vP'),
                'level' => strtoupper($level),
                'message' => $this->interpolate($message, $context),
                'channel' => $context['channel'] ?? 'app',
                'env' => $_ENV['APP_ENV'] ?? 'production',
            ];
            if (!empty($context)) {
                $entry['context'] = $context;
            }
            return json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        }

        $timestamp = date('Y-m-d H:i:s');
        $levelUpper = strtoupper($level);
        
        // Interpolate message with context placeholders
        $message = $this->interpolate($message, $context);
        
        // Build log entry
        $logEntry = "[{$timestamp}] {$levelUpper}: {$message}";
        
        // Add context if present
        if (!empty($context)) {
            $contextJson = json_encode($context, JSON_UNESCAPED_SLASHES);
            $logEntry .= " | Context: {$contextJson}";
        }
        
        return $logEntry . "\n";
    }

    /**
     * Interpolate context values into message placeholders
     *
     * @param string $message Message with placeholders
     * @param array $context Context data
     * @return string Interpolated message
     */
    private function interpolate(string $message, array $context): string
    {
        $replace = [];
        
        foreach ($context as $key => $val) {
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }
        
        return strtr($message, $replace);
    }

    /**
     * Get default log path
     *
     * @return string Default log file path
     */
    private function getDefaultLogPath(): string
    {
        $logDir = dirname(__DIR__) . '/storage/logs';
        return $logDir . '/app.log';
    }

    /**
     * Ensure log directory exists
     *
     * @return void
     */
    private function ensureLogDirectory(): void
    {
        $logDir = dirname($this->logPath);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
    }

    /**
     * Rotate log file if it exceeds maximum size
     *
     * @return void
     */
    private function rotateLogIfNeeded(): void
    {
        if (!file_exists($this->logPath)) {
            return;
        }

        if (filesize($this->logPath) < $this->maxFileSize) {
            return;
        }

        // Rotate existing files
        for ($i = $this->maxFiles - 1; $i >= 1; $i--) {
            $oldFile = $this->logPath . '.' . $i;
            $newFile = $this->logPath . '.' . ($i + 1);
            
            if (file_exists($oldFile)) {
                if ($i === $this->maxFiles - 1) {
                    unlink($oldFile); // Delete oldest file
                } else {
                    rename($oldFile, $newFile);
                }
            }
        }

        // Move current log to .1
        rename($this->logPath, $this->logPath . '.1');
    }

    /**
     * Format bytes to human-readable format
     *
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . $units[$pow];
    }

    /**
     * Set minimum log level
     *
     * @param string $level Minimum log level
     * @return void
     */
    public function setMinLevel(string $level): void
    {
        $this->minLevel = $level;
    }

    /**
     * Get current minimum log level
     *
     * @return string Current minimum log level
     */
    public function getMinLevel(): string
    {
        return $this->minLevel;
    }

    /**
     * Check if a log level is enabled
     *
     * @param string $level Log level to check
     * @return bool True if level is enabled
     */
    public function isLevelEnabled(string $level): bool
    {
        return $this->levels[$level] >= $this->levels[$this->minLevel];
    }
}