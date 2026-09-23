<?php
declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\WebhookService;

class WebhookServiceTest extends TestCase
{
    private WebhookService $service;
    private string $secret;

    protected function setUp(): void
    {
        $this->service = new WebhookService();
        $this->secret  = 'whsec_test_secret_12345';
    }

    // ── HMAC verification ─────────────────────────────────────────────────────

    public function test_hmac_verify_valid_signature(): void
    {
        $payload  = '{"event":"charge.succeeded"}';
        $signature = hash_hmac('sha256', $payload, $this->secret);

        $this->assertTrue($this->service->verify($payload, $signature, 'hmac', $this->secret));
    }

    public function test_hmac_verify_invalid_signature(): void
    {
        $payload  = '{"event":"charge.succeeded"}';
        $signature = 'invalid_signature';

        $this->assertFalse($this->service->verify($payload, $signature, 'hmac', $this->secret));
    }

    public function test_hmac_verify_empty_signature(): void
    {
        $this->assertFalse($this->service->verify('payload', '', 'hmac', $this->secret));
    }

    public function test_hmac_verify_empty_secret(): void
    {
        $this->assertFalse($this->service->verify('payload', 'sig', 'hmac', ''));
    }

    // ── Stripe verification ───────────────────────────────────────────────────

    public function test_stripe_verify_valid(): void
    {
        $payload  = '{"id":"ch_123"}';
        $timestamp = (string)time();
        $sig = hash_hmac('sha256', $timestamp . $payload, $this->secret);
        $header = "t={$timestamp},v1={$sig}";

        $this->assertTrue($this->service->verifyStripe($payload, $header, $this->secret));
    }

    public function test_stripe_verify_expired_timestamp(): void
    {
        $payload  = '{"id":"ch_123"}';
        $oldTime  = (string)(time() - 600); // 10 minutes ago
        $sig      = hash_hmac('sha256', $oldTime . $payload, $this->secret);
        $header   = "t={$oldTime},v1={$sig}";

        $this->assertFalse($this->service->verifyStripe($payload, $header, $this->secret));
    }

    public function test_stripe_verify_tampered_payload(): void
    {
        $payload  = '{"id":"ch_123"}';
        $timestamp = (string)time();
        $sig      = hash_hmac('sha256', $timestamp . $payload, $this->secret);
        $header   = "t={$timestamp},v1={$sig}";

        // Tamper with payload after signing
        $this->assertFalse($this->service->verifyStripe('{"id":"ch_TAMPERED"}', $header, $this->secret));
    }

    public function test_stripe_verify_empty_header(): void
    {
        $this->assertFalse($this->service->verifyStripe('payload', '', $this->secret));
    }

    // ── Twilio verification ───────────────────────────────────────────────────

    public function test_twilio_verify_valid(): void
    {
        $url      = 'https://example.com/webhook/twilio';
        $params   = ['From' => '+1234567890', 'Body' => 'Hello'];
        $signature = $this->service->generateTwilioSignature($url, $params, $this->secret);

        $this->assertTrue($this->service->verifyTwilio('', $signature, $this->secret, $params, $url));
    }

    public function test_twilio_verify_invalid(): void
    {
        $this->assertFalse($this->service->verifyTwilio('', 'wrong_sig', $this->secret, ['From' => '+1'], 'https://example.com/hook'));
    }

    public function test_twilio_verify_empty_signature(): void
    {
        $this->assertFalse($this->service->verifyTwilio('', '', $this->secret));
    }

    // ── Unified verify() ──────────────────────────────────────────────────────

    public function test_unified_verify_dispatches_correct_provider(): void
    {
        $payload = '{"event":"test"}';
        $sig = hash_hmac('sha256', $payload, $this->secret);

        $this->assertTrue($this->service->verify($payload, $sig, 'hmac', $this->secret));
        $this->assertTrue($this->service->verify($payload, $sig, 'HMAC', $this->secret));
    }

    public function test_unified_verify_unknown_provider_returns_false(): void
    {
        $this->assertFalse($this->service->verify('p', 's', 'unknown-provider', $this->secret));
    }
}
