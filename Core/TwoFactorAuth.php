<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Two-Factor Authentication (2FA) / MFA service.
 *
 * Implements TOTP (Time-based One-Time Password) using RFC 6238.
 * Uses libsodium for secure random generation and base32 encoding.
 *
 * Usage:
 *   $tfa = new TwoFactorAuth();
 *   $qrCode = $tfa->generateQRCode($user->email);         // get QR code SVG/data
 *   $tfa->verify($user->totp_secret, $code);              // verify 6-digit code
 *   $tfa->regenerate($userId);                            // rotate secret
 */
class TwoFactorAuth
{
    protected int $digits = 6;
    protected int $period = 30;
    protected string $algorithm = 'sha1';
    protected string $issuer;

    public function __construct(string $issuer = 'SENZFramework', int $digits = 6, int $period = 30)
    {
        $this->issuer = $issuer;
        $this->digits = $digits;
        $this->period = $period;
    }

    /**
     * Generate a new TOTP secret (base32-encoded).
     */
    public function generateSecret(int $bytes = 10): string
    {
        if (extension_loaded('sodium')) {
            return \Sodium\base64\encode(random_bytes($bytes));
        }
        // Fallback: bin2hex then base32-encode
        $raw = random_bytes($bytes);
        return $this->toBase32($raw);
    }

    /**
     * Convert raw bytes to base32 string.
     */
    protected function toBase32(string $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $result = '';
        $padding = '';
        $bits = 0;
        $value = 0;

        foreach (str_split(bin2hex($bytes)) as $hex) {
            $value = ($value << 4) | hexdec($hex);
            $bits += 4;
            if ($bits >= 5) {
                $bits -= 5;
                $result .= $alphabet[($value >> $bits) & 0x1f];
            }
        }

        if ($bits > 0) {
            $result .= $alphabet[($value << (5 - $bits)) & 0x1f];
        }

        while (strlen($result) % 8 !== 0) {
            $result .= '=';
        }

        return $result;
    }

    /**
     * Generate a QR code URI for provisioning (Google Authenticator, etc.).
     */
    public function generateQRCodeUri(string $email, string $secret): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=%s&digits=%d&period=%d',
            urlencode($this->issuer),
            urlencode($email),
            urlencode($secret),
            urlencode($this->issuer),
            strtoupper($this->algorithm),
            $this->digits,
            $this->period
        );
    }

    /**
     * Generate a QR code as SVG data URL.
     */
    public function generateQRCodeSvg(string $email, string $secret): string
    {
        $uri = $this->generateQRCodeUri($email, $secret);

        // Use an online QR API or generate locally with GD/imagick
        // For simplicity, return the URI — caller can use any QR library
        return $uri;
    }

    /**
     * Verify a TOTP code against a secret.
     * Allows ±1 period window for clock skew.
     */
    public function verify(string $secret, string $code, int $window = 1): bool
    {
        if (!preg_match('/^\d{' . $this->digits . '}$/', $code)) {
            return false;
        }

        $decoded = $this->decodeSecret($secret);
        if ($decoded === false) {
            return false;
        }

        $current = (int)floor(time() / $this->period);

        for ($i = -$window; $i <= $window; $i++) {
            if ($this->verifyCode($decoded, $current + $i, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the current TOTP code for a secret (for testing/display).
     */
    public function getCurrentCode(string $secret): string
    {
        $decoded = $this->decodeSecret($secret);
        if ($decoded === false) {
            return '';
        }
        return $this->generateCode($decoded, (int)floor(time() / $this->period));
    }

    /**
     * Regenerate a user's 2FA secret (rotation).
     */
    public function regenerate(string $userId): string
    {
        // In production: save new secret to DB, invalidate old one
        $newSecret = $this->generateSecret();
        // TODO: Persist $newSecret for $userId
        return $newSecret;
    }

    /**
     * Decode a base32 secret to raw bytes.
     */
    protected function decodeSecret(string $secret): false|string
    {
        $secret = strtoupper(trim($secret));
        $secret = preg_replace('/[^A-Z2-7=]/', '', $secret);

        if (extension_loaded('sodium')) {
            return \Sodium\base64\decode($secret);
        }

        // Manual base32 decode
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $result = '';
        $buffer = 0;
        $bits = 0;

        for ($i = 0; $i < strlen($secret); $i++) {
            $char = $secret[$i];
            if ($char === '=') continue;

            $idx = strpos($alphabet, $char);
            if ($idx === false) continue;

            $buffer = ($buffer << 5) | $idx;
            $bits += 5;

            if ($bits >= 8) {
                $bits -= 8;
                $result .= chr(($buffer >> $bits) & 0xff);
            }
        }

        return $result === '' ? false : $result;
    }

    /**
     * Verify a single time step against a code.
     */
    protected function verifyCode(string $key, int $counter, string $code): bool
    {
        return hash_equals(
            $this->generateCode($key, $counter),
            $code
        );
    }

    /**
     * Generate a TOTP code for a given counter.
     */
    protected function generateCode(string $key, int $counter): string
    {
        $counterBytes = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac($this->algorithm, $counterBytes, $key, true);

        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $binary = substr($hash, $offset, 4);
        $binary = unpack('N', $binary)[1] & 0x7FFFFFFF;
        $code = $binary % pow(10, $this->digits);

        return str_pad((string)$code, $this->digits, '0', STR_PAD_LEFT);
    }
}

/**
 * 2FA Middleware — protects routes requiring MFA verification.
 */
class TwoFactorMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Response $response, callable $next): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return $next($request, $response);
        }

        // Check if 2FA is enabled for this user
        $twoFactorEnabled = $this->isTwoFactorEnabled($user);
        if (!$twoFactorEnabled) {
            return $next($request, $response);
        }

        // Check if session has been verified
        $verified = $_SESSION['2fa_verified'] ?? false;
        if (!$verified) {
            $response->setStatusCode(403);
            $response->json([
                'success' => false,
                'message' => 'Two-factor authentication required',
                'requires_2fa' => true,
            ]);
            return $response;
        }

        return $next($request, $response);
    }

    protected function isTwoFactorEnabled(object $user): bool
    {
        if (method_exists($user, 'getMfaEnabled')) {
            return (bool)$user->getMfaEnabled();
        }
        if (isset($user->mfa_enabled)) {
            return (bool)$user->mfa_enabled;
        }
        return false;
    }
}
