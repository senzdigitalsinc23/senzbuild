<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Multi-Channel Notification Service — unified notifications across mail, SMS, Slack, DB.
 *
 * Usage:
 *   Notification::send($user, new WelcomeEmail());
 *   Notification::send($user, new LowStockAlert(['channel' => 'slack']));
 *
 * Channels: mail, sms, slack, db (database/In-App)
 */
class Notification
{
    /** @var array<string, callable> Channel handlers */
    protected static array $channels = [];

    /**
     * Register a notification channel handler.
     */
    public static function channel(string $name, callable $handler): void
    {
        self::$channels[$name] = $handler;
    }

    /**
     * Send a notification to a user through configured channels.
     *
     * @param object $user User model with contact info
     * @param object $notification Notification instance (must implement ShouldNotify)
     * @param array $channels Override default channels
     * @return array Sent channel results
     */
    public static function send(object $user, object $notification, array $channels = []): array
    {
        $defaultChannels = $notification->channels() ?? ['mail', 'db'];
        $targetChannels = empty($channels) ? $defaultChannels : $channels;
        $results = [];

        foreach ($targetChannels as $channel) {
            $handler = self::$channels[$channel] ?? null;
            if ($handler === null) {
                continue;
            }

            try {
                $result = $handler($user, $notification);
                $results[$channel] = ['status' => 'sent', 'result' => $result];
            } catch (\Throwable $e) {
                $results[$channel] = ['status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Send notification via a specific channel.
     */
    public static function sendVia(string $channel, object $user, object $notification): mixed
    {
        $handler = self::$channels[$channel] ?? null;
        if ($handler === null) {
            throw new \InvalidArgumentException("Notification channel '{$channel}' is not registered.");
        }
        return $handler($user, $notification);
    }

    /**
     * Get list of available channels.
     */
    public static function channels(): array
    {
        return array_keys(self::$channels);
    }
}

/**
 * Interface for notification objects.
 */
interface ShouldNotify
{
    /**
     * Get the channels this notification should be sent through.
     *
     * @return string[]|null
     */
    public function channels(): ?array;
}

/**
 * Base notification class.
 */
abstract class BaseNotification implements ShouldNotify
{
    /**
     * Default channels.
     *
     * @return string[]
     */
    public function channels(): array
    {
        return ['mail', 'db'];
    }
}
