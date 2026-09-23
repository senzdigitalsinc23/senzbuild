<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Webhook signature verification service.
 *
 * Supports multiple providers via a unified interface:
 *   - Stripe      (HMAC-SHA256)
 *   - Twilio      (SHA-256 with timestamp tolerance)
 *   - Generic HMAC (custom secret + algorithm)
 *
 * Usage:
 *   $svc = new WebhookService();
 *   $valid = $svc->verify($payload, $signature, 'stripe', $webhookSecret);
 */
class WebhookService
{
    /**
     * Verify a webhook signature.
     *
     * @param string      $payload      Raw request body
     * @param string      $signature    Signature string from the request header/payload
     * @param string      $provider     'stripe', 'twilio', or 'hmac'
     * @param string|null $secret       Signing secret (from env)
     * @param int         $timestampTolerance Seconds of allowed clock skew (default 300 = 5 min)
     * @return bool
     */
    public function verify(
        string $payload,
        string $signature,
        string $provider = 'hmac',
        ?string $secret = null,
        int $timestampTolerance = 300
    ): bool {
        $secret ??= $_ENV['WEBHOOK_SECRET'] ?? '';

        if (empty($secret)) {
            return false;
        }

        return match (strtolower($provider)) {
            'stripe'  => $this->verifyStripe($payload, $signature, $secret),
            'twilio'  => $this->verifyTwilio($payload, $signature, $secret),
            'hmac'    => $this->verifyHmac($payload, $signature, $secret),
            default   => false,
        };
    }

    /**
     * Verify Stripe webhook signature.
     *
     * Stripe sends signature in the 'Stripe-Signature' header as:
     *   t=<timestamp>,v1=<signature>
     */
    public function verifyStripe(string $payload, string $header, string $secret): bool
    {
        if (empty($header)) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $part) {
            $eq = strpos($part, '=');
            if ($eq === false) continue;
            $parts[substr($part, 0, $eq)] = substr($part, $eq + 1);
        }

        $timestamp = (int)($parts['t'] ?? 0);
        $signature = $parts['v1'] ?? '';

        if ($timestamp < time() - 300) {
            return false; // Timestamp older than 5 minutes
        }

        $expected = hash_hmac('sha256', (string)$timestamp . $payload, $secret);
        return hash_equals($expected, $signature);
    }

    /**
     * Verify Twilio webhook signature.
     *
     * Twilio sends signature in the 'X-Twilio-Signature' header.
     * The signature is SHA-256 of the URL + all POST params.
     */
    public function verifyTwilio(
        string $payload,
        string $signature,
        string $secret,
        array $postParams = [],
        string $url = ''
    ): bool {
        if (empty($signature)) {
            return false;
        }

        $url = $url ?: ($_SERVER['REQUEST_URI'] ?? '');
        $toSign = $url;

        ksort($postParams);
        foreach ($postParams as $key => $value) {
            $toSign .= $key . $value;
        }

        $expected = hash_hmac('sha256', $toSign, $secret);
        return hash_equals($expected, $signature);
    }

    /**
     * Generic HMAC-SHA256 verification.
     *
     * Expects the signature to be passed in the 'X-Webhook-Signature' header
     * or as the $signature parameter directly.
     */
    public function verifyHmac(string $payload, string $signature, string $secret): bool
    {
        if (empty($signature)) {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected, $signature);
    }

    /**
     * Generate a Twilio-compatible signature for testing.
     */
    public function generateTwilioSignature(
        string $url,
        array $params,
        string $secret
    ): string {
        ksort($params);
        $toSign = $url;
        foreach ($params as $key => $value) {
            $toSign .= $key . $value;
        }
        return hash_hmac('sha256', $toSign, $secret);
    }
}
