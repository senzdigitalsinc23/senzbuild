<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Sentry Integration — error tracking and exception reporting.
 *
 * Usage:
 *   Sentry::init(['dsn' => 'https://key@sentry.io/project']);
 *   Sentry::capture($exception);
 *   Sentry::setUser(['id' => $userId, 'email' => $email]);
 *
 * Also integrates with the framework's ErrorHandler for automatic reporting.
 */
class Sentry
{
    protected static ?string $dsn = null;
    protected static array $context = [];
    protected static bool $initialized = false;
    protected static array $lastEvents = [];

    /**
     * Initialize Sentry with DSN.
     */
    public static function init(string $dsn, array $options = []): void
    {
        self::$dsn = $dsn;
        self::$initialized = true;
        self::$context = $options;

        // If monolog-sentry is available, hook it in
        if (class_exists(\Monolog\Handler\SentryHandler::class)) {
            $handler = new \Monolog\Handler\SentryHandler($dsn, new \Sentry\Client($dsn, $options));
            Logger::getLogger()->pushHandler($handler);
        }
    }

    /**
     * Capture an exception and send to Sentry.
     */
    public static function capture(\Throwable $exception, array $extra = []): string
    {
        if (!self::$initialized) {
            self::$lastEvents[] = [
                'message' => $exception->getMessage(),
                'class'   => $exception::class,
                'file'    => $exception->getFile(),
                'line'    => $exception->getLine(),
                'extra'   => $extra,
            ];
            return '';
        }

        // Use Sentry SDK if available
        if (class_exists(\Sentry\ClientBuilder::class)) {
            $eventId = \Sentry\captureException($exception);
            return $eventId ?? '';
        }

        // Fallback: HTTP POST to Sentry
        self::sendToSentry($exception, $extra);

        return bin2hex(random_bytes(16));
    }

    /**
     * Capture a message/event.
     */
    public static function message(string $message, array $context = []): void
    {
        if (!self::$initialized) {
            self::$lastEvents[] = ['message' => $message, 'context' => $context];
            return;
        }

        if (class_exists(\Sentry\ClientBuilder::class)) {
            \Sentry\captureMessage($message, $context);
        }
    }

    /**
     * Set user context.
     */
    public static function setUser(array $user): void
    {
        self::$context['user'] = $user;
        if (class_exists(\Sentry\ClientBuilder::class)) {
            \Sentry\configureScope(function ($scope) use ($user) {
                $scope->setUser($user);
            });
        }
    }

    /**
     * Set tag.
     */
    public static function setTag(string $key, string $value): void
    {
        self::$context['tags'][$key] = $value;
    }

    /**
     * Set breadcrumb.
     */
    public static function breadcrumb(string $category, string $message, array $context = []): void
    {
        if (class_exists(\Sentry\ClientBuilder::class)) {
            \Sentry\addBreadcrumb(new \Sentry\Breadcrumb($category, \Sentry\Breadcrumb::LEVEL_INFO, null, $message, $context));
        }
    }

    /**
     * Get captured events (for testing without SDK).
     */
    public static function getEvents(): array
    {
        return self::$lastEvents;
    }

    /**
     * Reset state.
     */
    public static function reset(): void
    {
        self::$dsn = null;
        self::$context = [];
        self::$initialized = false;
        self::$lastEvents = [];
    }

    /**
     * Send event via HTTP to Sentry (fallback when SDK not available).
     */
    protected static function sendToSentry(\Throwable $exception, array $extra): void
    {
        $event = [
            'timestamp' => date('c'),
            'level' => 'error',
            'message' => $exception->getMessage(),
            'exception' => [
                'type' => $exception::class,
                'value' => $exception->getMessage(),
                'stacktrace' => [
                    'frames' => array_map(function ($frame) {
                        return ['filename' => $frame['file'], 'lineno' => $frame['line']];
                    }, $exception->getTrace()),
                ],
            ],
            'extra' => array_merge($extra, self::$context),
        ];

        $payload = json_encode(['event_id' => bin2hex(random_bytes(16)), ...$event], JSON_THROW_ON_ERROR);

        @file_get_contents(self::$dsn . '/api/latest/store/', false, stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => [
                    'Content-Type: application/json',
                    'X-Sentry-Auth: Sentry sentry_version=7, sentry_client=php-framework',
                ],
                'content' => $payload,
            ],
        ]));
    }
}
