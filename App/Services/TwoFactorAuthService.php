<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Two-Factor Authentication Service using TOTP
 */
class TwoFactorAuthService
{
    private const ALGORITHM = 'SHA1';
    private const DIGITS = 6;
    private const PERIOD = 30;
    private const ISSUER = 'API Project';

    /**
     * Generate a Base32-encoded secret (26 chars = 16 bytes)
     */
    public function generateSecret(): string
    {
        $bytes = random_bytes(16);
        return $this->base32Encode($bytes);
    }

    /**
     * Generate a TOTP code for a given secret and time
     */
    public function generateCode(string $secret, ?int $time = null): string
    {
        $time = $time ?? time();
        $timeStep = intdiv($time, self::PERIOD);

        $decodedSecret = $this->base32Decode($secret);
        if ($decodedSecret === false) {
            return '000000';
        }

        $binary = pack('N*', 0, $timeStep);
        $hmac = hash_hmac(self::ALGORITHM, $binary, $decodedSecret, true);

        $offset = ord($hmac[19]) & 0x0F;
        $code = unpack('N', substr($hmac, $offset, 4))[1] & 0x7FFFFFFF;
        $code = $code % pow(10, self::DIGITS);

        return str_pad((string)$code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a TOTP code
     */
    public function verify(string $secret, string $code, int $skew = 1): bool
    {
        if (empty($code) || strlen($code) !== self::DIGITS) {
            return false;
        }

        $now = time();
        for ($i = -$skew; $i <= $skew; $i++) {
            if ($this->generateCode($secret, $now + ($i * self::PERIOD)) === $code) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generate OTP Auth URI for QR code
     */
    public function getOtpAuthUri(string $secret, string $email, string $issuer = null): string
    {
        $issuer = $issuer ?? self::ISSUER;
        $label = rawurlencode($email);

        return "otpauth://totp/$issuer:$label?secret=$secret&issuer=$issuer&algorithm=" .
               self::ALGORITHM . "&digits=" . self::DIGITS . "&period=" . self::PERIOD;
    }

    /**
     * Generate backup codes (uppercase hex)
     */
    public function generateBackupCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(6)));
        }
        return $codes;
    }

    /**
     * Verify a backup code and return remaining codes
     */
    public function verifyBackupCode(string $code, array $codes): ?array
    {
        $index = array_search($code, $codes, true);
        if ($index === false) {
            return null;
        }
        array_splice($codes, $index, 1);
        return $codes;
    }

    /**
     * Base32 encode (no padding)
     */
    private function base32Encode(string $data): string
    {
        $bytes = str_split($data, 1);
        $result = '';
        $buffer = 0;
        $bitsLeft = 0;

        foreach ($bytes as $byte) {
            $buffer = ($buffer << 8) | ord($byte);
            $bitsLeft += 8;

            while ($bitsLeft >= 5) {
                $result .= substr('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', ($buffer >> ($bitsLeft - 5)) & 31, 1);
                $bitsLeft -= 5;
            }
        }

        if ($bitsLeft > 0) {
            $result .= substr('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', ($buffer << (5 - $bitsLeft)) & 31, 1);
        }

        return rtrim($result, '=');
    }

    /**
     * Base32 decode
     */
    private function base32Decode(string $encoded): bool|string
    {
        $encoded = strtoupper(trim($encoded));
        $encoded = str_replace('=', '', $encoded);

        $bits = '';
        foreach (str_split($encoded) as $char) {
            $index = strpos('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', $char);
            if ($index === false) {
                return false;
            }
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $result = '';
        for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
            $result .= chr(bindec(substr($bits, $i, 8)));
        }

        return $result;
    }
}
