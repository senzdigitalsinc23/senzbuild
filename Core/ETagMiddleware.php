<?php
declare(strict_types=1);

namespace App\Core;

/**
 * ETag middleware — adds HTTP ETag caching to responses.
 *
 * Usage:
 *   $router->middleware([\App\Core\ETagMiddleware::class]);
 */
class ETagMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Response $response, callable $next): Response
    {
        $response = $next($request, $response);

        // Only apply to successful GET responses with JSON content
        if ($response->getStatusCode() !== 200) {
            return $response;
        }

        $contentType = $response->getCustomHeader('Content-Type');
        if ($contentType !== 'application/json') {
            return $response;
        }

        $content = $response->getContent();
        if ($content === '') {
            return $response;
        }

        // Generate ETag from content hash
        $etag = '"' . hash('xxh128', $content) . '"';

        // Check If-None-Match
        $ifNoneMatch = $request->getHeader('If-None-Match');
        if ($ifNoneMatch !== null && $ifNoneMatch === $etag) {
            $response->setStatusCode(304);
            $response->setContent('');
            $response->setHeader('ETag', $etag);
            return $response;
        }

        $response->setHeader('ETag', $etag);
        $response->setHeader('Cache-Control', 'private, max-age=0, must-revalidate');

        return $response;
    }
}
