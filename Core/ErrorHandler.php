<?php
declare(strict_types=1);

namespace App\Core;

use App\Exceptions\ExceptionHandler;
use Throwable;

class ErrorHandler
{
    protected Logger $logger;
    protected bool $displayErrors;

    public function __construct(Logger $logger, bool $displayErrors = false)
    {
        $this->logger = $logger;
        $this->displayErrors = $displayErrors;
    }

    public function register(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');

        // Use Whoops in development for beautiful error pages
        if (env('APP_DEBUG', false) && class_exists(\Whoops\Run::class)) {
            $whoops = new \Whoops\Run();
            $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler());
            $whoops->register();
            return;
        }

        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    public function handleError(int $level, string $message, string $file, int $line): bool
    {
        throw new \ErrorException($message, 0, $level, $file, $line);
    }

    public function handleException(Throwable $exception): void
    {
        $this->logger->error(
            "Uncaught Exception: {$exception->getMessage()} in {$exception->getFile()} on line {$exception->getLine()}",
            ['trace' => $exception->getTrace()]
        );

        while (ob_get_level()) {
            ob_end_clean();
        }

        $response = ExceptionHandler::handle($exception);
        $response->send();
        exit(1);
    }

    public function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->logger->error("* Fatal Error: {$error['message']} in {$error['file']} on line {$error['line']}");

            while (ob_get_level()) {
                ob_end_clean();
            }

            $response = new Response();
            $response->json(ApiResponse::serverError('A fatal error occurred'), 500);
            exit(1);
        }
    }
}
