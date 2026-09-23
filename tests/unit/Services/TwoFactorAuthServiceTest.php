<?php

namespace Tests\Unit\Services;

use App\Services\TwoFactorAuthService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(TwoFactorAuthService::class)]
class TwoFactorAuthServiceTest extends TestCase
{
    private TwoFactorAuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TwoFactorAuthService();
    }

    #[Test]
    public function generate_secret_returns_base32_string(): void
    {
        $secret = $this->service->generateSecret();
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+=*$/', $secret);
    }

    #[Test]
    public function generate_secret_is_26_chars_long(): void
    {
        $secret = $this->service->generateSecret();
        $this->assertEquals(26, strlen($secret));
    }

    #[Test]
    public function generate_secret_produces_unique_values(): void
    {
        $s1 = $this->service->generateSecret();
        $s2 = $this->service->generateSecret();
        $this->assertNotEquals($s1, $s2);
    }

    #[Test]
    public function generate_code_returns_6_digit_string(): void
    {
        $secret = $this->service->generateSecret();
        $code = $this->service->generateCode($secret);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
    }

    #[Test]
    public function generate_code_is_deterministic_for_same_time(): void
    {
        $secret = $this->service->generateSecret();
        $time = 1234567890;
        $code1 = $this->service->generateCode($secret, $time);
        $code2 = $this->service->generateCode($secret, $time);
        $this->assertEquals($code1, $code2);
    }

    #[Test]
    public function generate_code_differs_for_different_times(): void
    {
        $secret = $this->service->generateSecret();
        $code1 = $this->service->generateCode($secret, 1000000);
        $code2 = $this->service->generateCode($secret, 2000000);
        $this->assertNotEquals($code1, $code2);
    }

    #[Test]
    public function verify_returns_true_for_valid_code(): void
    {
        $secret = $this->service->generateSecret();
        $code = $this->service->generateCode($secret);
        $this->assertTrue($this->service->verify($secret, $code));
    }

    #[Test]
    public function verify_returns_false_for_invalid_code(): void
    {
        $secret = $this->service->generateSecret();
        $this->assertFalse($this->service->verify($secret, '000000'));
    }

    #[Test]
    public function verify_accepts_code_from_adjacent_step(): void
    {
        $secret = $this->service->generateSecret();
        $now = time();
        $codeFromPast = $this->service->generateCode($secret, $now - 30);
        $this->assertTrue($this->service->verify($secret, $codeFromPast));
    }

    #[Test]
    public function verify_rejects_code_from_two_steps_ago_with_default_skew(): void
    {
        $secret = $this->service->generateSecret();
        $now = time();
        $codeFromFarPast = $this->service->generateCode($secret, $now - 60);
        $this->assertFalse($this->service->verify($secret, $codeFromFarPast));
    }

    #[Test]
    public function verify_respects_custom_skew(): void
    {
        $secret = $this->service->generateSecret();
        $now = time();
        $codeFromFarPast = $this->service->generateCode($secret, $now - 60);
        $this->assertTrue($this->service->verify($secret, $codeFromFarPast, 2));
    }

    #[Test]
    public function verify_empty_string_returns_false(): void
    {
        $secret = $this->service->generateSecret();
        $this->assertFalse($this->service->verify($secret, ''));
    }

    #[Test]
    public function get_otp_auth_uri_contains_required_parameters(): void
    {
        $secret = $this->service->generateSecret();
        $uri = $this->service->getOtpAuthUri($secret, 'user@test.com');
        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret=' . $secret, $uri);
        $this->assertStringContainsString('issuer=API Project', $uri);
        $this->assertStringContainsString('algorithm=SHA1', $uri);
        $this->assertStringContainsString('digits=6', $uri);
        $this->assertStringContainsString('period=30', $uri);
    }

    #[Test]
    public function get_otp_auth_uri_encodes_label(): void
    {
        $uri = $this->service->getOtpAuthUri('SECRET', 'user@test.com', 'TestIssuer');
        $this->assertStringContainsString('user%40test.com', $uri);
        $this->assertStringContainsString('issuer=TestIssuer', $uri);
    }

    #[Test]
    public function generate_backup_codes_returns_default_count(): void
    {
        $codes = $this->service->generateBackupCodes();
        $this->assertCount(8, $codes);
    }

    #[Test]
    public function generate_backup_codes_returns_custom_count(): void
    {
        $codes = $this->service->generateBackupCodes(5);
        $this->assertCount(5, $codes);
    }

    #[Test]
    public function generate_backup_codes_returns_unique_values(): void
    {
        $codes = $this->service->generateBackupCodes(8);
        $this->assertEquals(8, count(array_unique($codes)));
    }

    #[Test]
    public function generate_backup_codes_each_code_is_12_hex_chars(): void
    {
        $codes = $this->service->generateBackupCodes(3);
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[A-F0-9]{12}$/', $code);
        }
    }

    #[Test]
    public function verify_backup_code_returns_remaining_codes(): void
    {
        $codes = ['CODE1', 'CODE2', 'CODE3'];
        $remaining = $this->service->verifyBackupCode('CODE2', $codes);
        $this->assertNotNull($remaining);
        $this->assertCount(2, $remaining);
        $this->assertNotContains('CODE2', $remaining);
    }

    #[Test]
    public function verify_backup_code_returns_null_for_unknown_code(): void
    {
        $codes = ['CODE1', 'CODE2'];
        $result = $this->service->verifyBackupCode('UNKNOWN', $codes);
        $this->assertNull($result);
    }

    #[Test]
    public function verify_backup_code_returns_original_list_if_no_match(): void
    {
        $codes = ['CODE1', 'CODE2'];
        $result = $this->service->verifyBackupCode('WRONG', $codes);
        $this->assertNull($result);
    }

    #[Test]
    public function base32_roundtrip_maintains_data(): void
    {
        $ref = new \ReflectionClass($this->service);
        $encode = $ref->getMethod('base32Encode');
        $decode = $ref->getMethod('base32Decode');
        $encode->setAccessible(true);
        $decode->setAccessible(true);

        $original = random_bytes(16);
        $encoded = $encode->invoke($this->service, $original);
        $decoded = $decode->invoke($this->service, $encoded);

        $this->assertEquals($original, $decoded);
    }
}
