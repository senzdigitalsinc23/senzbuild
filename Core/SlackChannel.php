<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Slack notification channel — send notifications via Slack webhook.
 *
 * Usage:
 *   Notification::channel('slack', function($user, $notification) {
 *       return SlackChannel::send($notification, $user);
 *   });
 *
 *   // Or use the built-in SlackMessage class:
 *   Notification::send($user, new SlackMessage('Hello from the framework!'));
 */
class SlackChannel
{
    protected string $webhookUrl;
    protected string $botName;
    protected string $icon;

    public function __construct(string $webhookUrl, string $botName = 'FrameworkBot', string $icon = ':robot_face:')
    {
        $this->webhookUrl = $webhookUrl;
        $this->botName = $botName;
        $this->icon = $icon;
    }

    /**
     * Send a notification to Slack.
     *
     * @param object $notification Notification instance
     * @param object $user User model
     * @return array{status: string, slack_message_id?: string}
     * @throws \RuntimeException If webhook fails
     */
    public static function send(object $notification, object $user): array
    {
        $webhookUrl = Config::get('slack.webhook_url', '');
        if (empty($webhookUrl)) {
            throw new \RuntimeException('Slack webhook URL not configured');
        }

        $text = self::formatMessage($notification, $user);
        $payload = self::buildPayload($text, $notification);

        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \RuntimeException("Slack API returned HTTP {$httpCode}: {$response}");
        }

        return ['status' => 'sent'];
    }

    /**
     * Format notification text for Slack.
     */
    protected static function formatMessage(object $notification, object $user): string
    {
        if (method_exists($notification, 'toSlack')) {
            return $notification->toSlack($user);
        }

        $name = method_exists($user, 'getAttribute')
            ? $user->name ?? $user->email ?? 'User'
            : get_class($user);

        return "**{$name}**: " . (method_exists($notification, 'getMessage') ? $notification->getMessage() : get_class($notification));
    }

    /**
     * Build the Slack webhook payload.
     */
    protected static function buildPayload(string $text, object $notification): array
    {
        $payload = [
            'text' => $text,
            'username' => Config::get('slack.bot_name', 'FrameworkBot'),
            'icon_emoji' => Config::get('slack.icon_emoji', ':robot_face:'),
        ];

        // Add blocks if notification has structured data
        if (method_exists($notification, 'getBlocks')) {
            $payload['blocks'] = $notification->getBlocks();
        }

        // Add attachment for rich formatting
        if (method_exists($notification, 'getAttachment')) {
            $payload['attachments'] = [$notification->getAttachment()];
        }

        return $payload;
    }

    /**
     * Send a quick message to a Slack channel.
     */
    public static function message(string $channel, string $message, array $options = []): array
    {
        $webhookUrl = Config::get('slack.webhook_url', '');
        if (empty($webhookUrl)) {
            throw new \RuntimeException('Slack webhook URL not configured');
        }

        $payload = [
            'channel' => $channel,
            'text' => $message,
            ...$options,
        ];

        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $httpCode === 200 ? 'sent' : 'failed',
            'http_code' => $httpCode,
            'response' => $response,
        ];
    }
}

/**
 * Slack notification message class.
 */
class SlackMessage
{
    protected string $text;
    protected array $blocks = [];
    protected ?array $attachment = null;

    public function __construct(string $text)
    {
        $this->text = $text;
    }

    public function withBlocks(array $blocks): self
    {
        $this->blocks = $blocks;
        return $this;
    }

    public function withAttachment(array $attachment): self
    {
        $this->attachment = $attachment;
        return $this;
    }

    public function getMessage(): string
    {
        return $this->text;
    }

    public function getBlocks(): array
    {
        return $this->blocks;
    }

    public function getAttachment(): ?array
    {
        return $this->attachment;
    }

    public function toSlack(object $user): string
    {
        return $this->text;
    }
}
