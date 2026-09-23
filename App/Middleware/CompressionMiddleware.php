<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

class CompressionMiddleware
{
    public function handle(Request $request, Response $response, callable $next): Response
    {
        $response = $next($request, $response);

        // Only compress successful JSON responses
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300 &&
            str_contains($response->getHeaderLine('Content-Type'), 'application/json')) {


            $acceptEncoding = $request->getHeader('Accept-Encoding') ?? '';
            $content = $response->getContent();

            if (str_contains($acceptEncoding, 'gzip')) {
                $compressed = gzencode($content, 6);
                if ($compressed !== false) {
                    $response->setContent($compressed);
                    $response->setHeader('Content-Encoding', 'gzip');
                    $response->setHeader('Vary', 'Accept-Encoding');
                    // Update Content-Length if it was set
                    if ($response->hasHeader('Content-Length')) {
                        $response->setHeader('Content-Length', (string)strlen($compressed));
                    }
                }
            } elseif (str_contains($acceptEncoding, 'deflate')) {
                $compressed = gzcompress($content, 6);
                if ($compressed !== false) {
                    $response->setContent($compressed);
                    $response->setHeader('Content-Encoding', 'deflate');
                    $response->setHeader('Vary', 'Accept-Encoding');
                    if ($response->hasHeader('Content-Length')) {
                        $response->setHeader('Content-Length', (string)strlen($compressed));
                    }
                }
            }
        }

        return $response;
    }
}
