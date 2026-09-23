<?php
declare(strict_types=1);

namespace App\Core;

interface MiddlewareInterface
{
    public function handle(Request $request, Response $response, callable $next): Response;
}
