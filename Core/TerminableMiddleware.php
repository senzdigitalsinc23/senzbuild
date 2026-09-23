<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Trait for terminable middleware — adds an optional terminate() hook
 * called after the response is sent.
 *
 * Use this trait in middleware classes that need post-response cleanup:
 *   class LogRequestMiddleware implements MiddlewareInterface {
 *       use TerminableMiddleware;
 *       // ...
 *   }
 */
trait TerminableMiddleware
{
    public function terminate(Request $request, Response $response): void
    {
        // Override in the using class
    }
}
