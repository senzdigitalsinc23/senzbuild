<?php
declare(strict_types=1);

namespace App\Exceptions;

use App\Core\Response;
use App\Core\ApiResponse;
use App\Core\LoggerFactory;
use Throwable;
use PDOException;

/**
 * Global exception handler for the application
 *
 * Converts exceptions to standardized JSON responses
 */
class ExceptionHandler
{
    /**
     * Handle an exception and return appropriate response
     */
    public static function handle(Throwable $e): Response
    {
        $response = new Response();

        // Handle custom application exceptions
        if ($e instanceof BaseException) {
            return self::handleBaseException($e, $response);
        }

        // Handle PDO exceptions
        if ($e instanceof PDOException) {
            return self::handlePDOException($e, $response);
        }

        // Handle generic exceptions
        return self::handleGenericException($e, $response);
    }

    /**
     * Handle BaseException instances
     */
    private static function handleBaseException(BaseException $e, Response $response): Response
    {
        $errorData = ApiResponse::error(
            $e->getMessage(),
            $e->getContext(),
            $e->getStatusCode(),
            $e->getErrorCode()
        );

        $response->setStatusCode($e->getStatusCode());
        $response->setHeader('Content-Type', 'application/json');
        $response->setContent(json_encode($errorData));

        // Log the exception
        self::logException($e);

        return $response;
    }

    /**
     * Handle PDOException instances
     */
    private static function handlePDOException(PDOException $e, Response $response): Response
    {
        $dbException = DatabaseException::fromPDO($e);
        return self::handleBaseException($dbException, $response);
    }

    /**
     * Handle generic exceptions
     */
    private static function handleGenericException(Throwable $e, Response $response): Response
    {
        $isDebug = ($_ENV['APP_DEBUG'] ?? false) === true || ($_ENV['APP_DEBUG'] ?? 'false') === 'true';

        $message = $isDebug ? $e->getMessage() : 'An internal error occurred';
        $errors = $isDebug ? [
            'trace' => $e->getTrace(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ] : null;

        $errorData = ApiResponse::serverError($message, $errors);

        $response->setStatusCode(500);
        $response->setHeader('Content-Type', 'application/json');
        $response->setContent(json_encode($errorData));

        // Log the exception
        self::logException($e);

        return $response;
    }

    /**
     * Log exception to error log
     */
    private static function logException(Throwable $e): void
    {
        LoggerFactory::getInstance()->error(
            sprintf(
                "Exception: %s: %s in %s:%d",
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ),
            [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]
        );
    }

    /**
     * Render exception as JSON (for use in catch blocks)
     */
    public static function toJson(Throwable $e): string
    {
        if ($e instanceof BaseException) {
            return json_encode(ApiResponse::error(
                $e->getMessage(),
                $e->getContext(),
                $e->getStatusCode(),
                $e->getErrorCode()
            ), JSON_PRETTY_PRINT);
        }

        $isDebug = ($_ENV['APP_DEBUG'] ?? false) === true || ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
        $message = $isDebug ? $e->getMessage() : 'An internal error occurred';
        $errors = $isDebug ? [
            'trace' => $e->getTrace(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ] : null;

        return json_encode(ApiResponse::serverError($message, $errors), JSON_PRETTY_PRINT);
    }

    /**
     * Get HTTP status code from exception
     */
    public static function getStatusCode(Throwable $e): int
    {
        if ($e instanceof BaseException) {
            return $e->getStatusCode();
        }

        if ($e instanceof PDOException) {
            return 500;
        }

        return 500;
    }
}
