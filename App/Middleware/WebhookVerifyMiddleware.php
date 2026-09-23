<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Services\WebhookService;

/**
 * Webhook signature verification middleware.
 *
 * Protects webhook endpoints from spoofed or tampered requests.
 *
 * Required headers (depending on provider):
 *   Stripe  → X-Stripe-Signature or Stripe-Signature
 *   Twilio  → X-Twilio-Signature
 *   Generic → X-Webhook-Signature
 *
 * Configuration:
 *   WEBHOOK_PROVIDER  = 'stripe'|'twilio'|'hmac'  (default: 'hmac')
 *   WEBHOOK_SECRET    = signing secret
 *
 * Usage in routes:
 *   $router->postApi('v1', '/webhook/stripe', [WebhookController::class, 'handle'], [WebhookVerifyMiddleware::class]);
 */
class WebhookVerifyMiddleware implements MiddlewareInterface
{
    private WebhookService $service;
    private string $provider;
    private string $secret;
    private int $statusCodeOnFailure;
    private string $headerName;

    public function __construct(
        ?WebhookService  $service           = null,
        ?string           $provider          = null,
        ?string           $secret            = null,
        int              $statusCodeOnFailure = 401,
        string           $headerName        = 'X-Webhook-Signature'
    ) {
        $this->service             = $service ?? new WebhookService();
        $this->provider            = $provider ?? ($_ENV['WEBHOOK_PROVIDER'] ?? 'hmac');
        $this->secret              = $secret ?? ($_ENV['WEBHOOK_SECRET'] ?? '');
        $this->statusCodeOnFailure = $statusCodeOnFailure;
        $this->headerName          = $headerName;
    }

    public function handle(Request $request, Response $response, callable $next): Response
    {
        $signature = $this->extractSignature($request);
        $payload   = (string)$request->getBody();

        if (empty($signature)) {
            return $this->forbidden($response, 'Missing webhook signature');
        }

        $valid = match ($this->provider) {
            'stripe'  => $this->service->verifyStripe($payload, $signature, $this->secret),
            'twilio'  => $this->service->verifyTwilio($payload, $signature, $this->secret, $request->getBodyParams(), $request->getPath()),
            default   => $this->service->verifyHmac($payload, $signature, $this->secret),
        };

        if (!$valid) {
            return $this->forbidden($response, 'Invalid webhook signature');
        }

        return $next($request, $response);
    }

    private function extractSignature(Request $request): string
    {
        // Try standard header names
        $headerNames = [
            'Stripe-Signature',
            'X-Stripe-Signature',
            'X-Twilio-Signature',
            $this->headerName,
        ];

        foreach ($headerNames as $name) {
            $value = $request->getHeaderLine($name);
            if (!empty($value)) {
                return trim($value);
            }
        }

        // Fall back to body param (some providers put it there)
        $body = $request->getBodyParams();
        return trim($body['signature'] ?? $body['sig'] ?? '');
    }

    private function forbidden(Response $response, string $message): Response
    {
        $response->setStatusCode($this->statusCodeOnFailure);
        $response->setHeader('Content-Type', 'application/json');
        $response->setContent(json_encode([
            'success' => false,
            'error'   => 'WEBHOOK_UNAUTHORIZED',
            'message' => $message,
        ]));
        return $response;
    }
}
