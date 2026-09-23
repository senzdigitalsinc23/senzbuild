<?php
declare(strict_types=1);

namespace Tests\Factory;

class MailFake
{
    protected static array $sent = [];

    public static function send($mailable): void
    {
        self::$sent[] = $mailable;
    }

    public static function queue($mailable): void
    {
        self::$sent[] = ['queued' => true, 'mailable' => $mailable];
    }

    public static function assertSent($mailable, ?callable $callback = null): void
    {
        foreach (self::$sent as $sent) {
            $actual = is_array($sent) ? $sent['mailable'] : $sent;
            if ($actual === $mailable || ($callback && $callback($actual))) {
                return;
            }
        }
        throw new \Exception("Expected mailable to be sent but it was not.");
    }

    public static function assertNothingSent(): void
    {
        if (!empty(self::$sent)) {
            throw new \Exception("Expected no mails to be sent but got " . count(self::$sent) . ".");
        }
    }

    public static function clear(): void
    {
        self::$sent = [];
    }
}
