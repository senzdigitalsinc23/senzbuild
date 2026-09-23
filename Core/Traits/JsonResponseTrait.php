<?php
declare(strict_types=1);

namespace App\Core\Traits;

use App\Core\Response;

/**
 * Provides a standardized json() response helper for API controllers.
 *
 * Every API v1 controller duplicates this exact method. Use this trait
 * to eliminate the duplication and ensure consistent JSON responses.
 */
trait JsonResponseTrait
{
    protected function json(Response $response, int $status, array $data): Response
    {
        $response->setStatusCode($status);
        $response->setHeader('Content-Type', 'application/json');
        $response->setContent(json_encode($data));
        return $response;
    }
}
