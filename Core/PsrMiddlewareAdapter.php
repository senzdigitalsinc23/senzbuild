<?php
declare(strict_types=1);

namespace App\Core;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface as PsrMiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Adapter that wraps the framework's custom middleware into a PSR-15 middleware.
 *
 * Use this to run existing App\Core\MiddlewareInterface implementations
 * inside a PSR-15 compliant pipeline.
 */
class PsrMiddlewareAdapter implements PsrMiddlewareInterface
{
    private MiddlewareInterface $middleware;

    public function __construct(MiddlewareInterface $middleware)
    {
        $this->middleware = $middleware;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $fwRequest  = new Request();
        $fwResponse = new Response();

        // Transfer PSR-7 attributes to framework request
        foreach ($request->getAttributes() as $key => $value) {
            $fwRequest->setAttribute($key, $value);
        }

        $result = $this->middleware->handle($fwRequest, $fwResponse, function ($req, $res) use ($handler, $request) {
            // Call the PSR-15 handler and convert response back
            $psrResponse = $handler->handle($request);
            $res->setStatusCode($psrResponse->getStatusCode());
            foreach ($psrResponse->getHeaders() as $name => $values) {
                foreach ($values as $value) {
                    $res->setHeader($name, $value);
                }
            }
            $res->setContent((string)$psrResponse->getBody());
            return $res;
        });

        // Convert framework response to PSR-7
        $psrResponse = \GuzzleHttp\Psr7\Response::fromMessage(
            new \GuzzleHttp\Psr7\Response(
                $result->getStatusCode(),
                $result->getHeaders(),
                $result->getContent()
            )
        );

        return $psrResponse;
    }
}
