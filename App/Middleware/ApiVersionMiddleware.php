<?php
declare(strict_types=1);


namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

class ApiVersionMiddleware
{
    private const SUPPORTED_VERSIONS = ['v1'];
    private const DEFAULT_VERSION = 'v1';

    public function handle(Request $request, Response $response, callable $next): Response
    {
        $version = self::DEFAULT_VERSION;

        // Check Accept header for version negotiation
        // e.g., Accept: application/vnd.multishop.v1+json
        $accept = $request->getHeaderLine('Accept');
        if (preg_match('/application\/vnd\.multishop\.(v\d+)\+json/', $accept, $m)) {
            $requested = $m[1];
            if (in_array($requested, self::SUPPORTED_VERSIONS, true)) {
                $version = $requested;
            }
        }

        // Also allow explicit X-API-Version header
        $headerVersion = $request->getHeaderLine('X-API-Version');
        if ($headerVersion && in_array($headerVersion, self::SUPPORTED_VERSIONS, true)) {
            $version = $headerVersion;
        }

        // Attach version to request for downstream use
        $request->setAttribute('api_version', $version);

        $response = $next($request, $response);

        // Tag response with version info
        if ($response instanceof Response) {
            $response->setHeader('X-API-Version', $version);
        }

        return $response;
    }
}
