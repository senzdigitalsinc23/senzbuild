<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Log Channels — predefined logger configurations for various output destinations.
 *
 * Provides factory methods for creating log channels.
 *
 * Available channels:
 * - syslog    -> SyslogLogger (system syslog, Linux/macOS)
 * - stackdriver -> StackdriverLogger (Google Cloud Logging)
 *
 * Usage:
 *   $logger = LogChannels::channel('syslog');
 *   $logger->info('Hello world');
 */
class LogChannels
{
    /**
     * Get a logger channel by name.
     */
    public static function channel(string $name, array $options = []): \Psr\Log\LoggerInterface
    {
        return match ($name) {
            'syslog' => new SyslogLogger(
                $options['facility'] ?? LOG_USER,
                $options['ident'] ?? ''
            ),
            'stackdriver' => new StackdriverLogger(
                $options['projectId'] ?? 'default-project',
                $options['logName'] ?? 'default'
            ),
            default => throw new \InvalidArgumentException("Unknown log channel: {$name}"),
        };
    }
}
