<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\TwoFactorAuth;

class TwoFactorAuthTest extends TestCase
{
    private TwoFactorAuth $tfa;

    protected function setUp(): void
    {
        $this->tfa = new TwoFactorAuth();
    }

    public function test_generate_secret(): void
    {
        $secret = $this->tfa->generateSecret();
        $this->assertNotEmpty($secret);
        $this->assertTrue(ctype_alnum($secret));
        $this->assertGreaterThan(0, strlen($secret));
    }

    public function test_qr_code_uri_format(): void
    {
        $uri = $this->tfa->generateQRCodeUri('user@example.com', 'JBSWY3DPEHPK3PXP');
        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('user%40example.com', $uri);
        $this->assertStringContainsString('JBSWY3DPEHPK3PXP', $uri);
    }

    public function test_verify_valid_code(): void
    {
        $secret = $this->tfa->generateSecret();
        $code = $this->tfa->getCurrentCode($secret);
        $this->assertTrue($this->tfa->verify($secret, $code));
    }

    public function test_verify_invalid_code(): void
    {
        $secret = $this->tfa->generateSecret();
        $this->assertFalse($this->tfa->verify($secret, '000000'));
    }

    public function test_verify_wrong_length(): void
    {
        $secret = $this->tfa->generateSecret();
        $this->assertFalse($this->tfa->verify($secret, '12345'));
    }

    public function test_regenerate(): void
    {
        $newSecret = $this->tfa->regenerate('user-1');
        $this->assertNotEmpty($newSecret);
        $this->assertNotSame('user-1', $newSecret);
    }
}
