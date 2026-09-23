<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Password Policy — enforce complexity rules at the framework level.
 *
 * Usage:
 *   Policy::validate('MyP@ssw0rd!');           // returns ['valid' => true, 'score' => 4]
 *   Policy::check('weak');                     // throws ValidationException
 *   Policy::needsRehash($hashed, ['min_length' => 12]);
 *
 * Configurable via config/app.php:
 *   'password_policy' => [
 *       'min_length'    => 8,
 *       'require_upper' => true,
 *       'require_lower' => true,
 *       'require_digit' => true,
 *       'require_special' => true,
 *       'no_common'     => true,
 *   ]
 */
class PasswordPolicy
{
    protected static array $policy = [
        'min_length'    => 8,
        'require_upper' => true,
        'require_lower' => true,
        'require_digit' => true,
        'require_special' => true,
        'no_common'     => true,
        'no_username'   => true,
    ];

    /**
     * Common passwords to reject (top 10k list subset).
     */
    protected static array $commonPasswords = [
        'password', '123456', '12345678', 'qwerty', 'abc123',
        'monkey', '1234567', 'letmein', 'trustno1', 'dragon',
        'baseball', 'iloveyou', 'master', 'sunshine', 'ashley',
        'michael', 'shadow', 'maggie', 'passw0rd', 'welcome',
    ];

    /**
     * Set policy rules.
     */
    public static function setPolicy(array $rules): void
    {
        self::$policy = array_merge(self::$policy, $rules);
    }

    /**
     * Get current policy.
     */
    public static function getPolicy(): array
    {
        return self::$policy;
    }

    /**
     * Validate a password against policy.
     *
     * @param string $password
     * @param string|null $username Optional username to check against
     * @return array{valid: bool, score: int, errors: string[]}
     */
    public static function validate(string $password, ?string $username = null): array
    {
        $errors = [];
        $score = 0;
        $policy = self::$policy;

        // Length
        $len = mb_strlen($password);
        if ($len < ($policy['min_length'] ?? 8)) {
            $errors[] = "Password must be at least {$policy['min_length']} characters";
        } else {
            $score += 2;
        }

        if ($len >= 12) {
            $score += 1;
        }

        // Character class requirements
        if ($policy['require_upper'] && preg_match('/[A-Z]/', $password)) {
            $score += 1;
        } elseif ($policy['require_upper']) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }

        if ($policy['require_lower'] && preg_match('/[a-z]/', $password)) {
            $score += 1;
        } elseif ($policy['require_lower']) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }

        if ($policy['require_digit'] && preg_match('/[0-9]/', $password)) {
            $score += 1;
        } elseif ($policy['require_digit']) {
            $errors[] = 'Password must contain at least one digit';
        }

        if ($policy['require_special'] && preg_match('/[^A-Za-z0-9]/', $password)) {
            $score += 1;
        } elseif ($policy['require_special']) {
            $errors[] = 'Password must contain at least one special character';
        }

        // No common passwords
        if ($policy['no_common'] && in_array(strtolower($password), self::$commonPasswords, true)) {
            $errors[] = 'Password is too common';
        }

        // No username in password
        if ($policy['no_username'] && $username !== null && stripos($password, $username) !== false) {
            $errors[] = 'Password cannot contain your username';
        }

        return [
            'valid'  => empty($errors),
            'score'  => min(10, $score),
            'errors' => $errors,
        ];
    }

    /**
     * Check password and throw on failure.
     *
     * @param string $password
     * @param string|null $username
     * @throws \InvalidArgumentException
     */
    public static function check(string $password, ?string $username = null): void
    {
        $result = self::validate($password, $username);
        if (!$result['valid']) {
            throw new \InvalidArgumentException(implode('; ', $result['errors']));
        }
    }

    /**
     * Check if a hash needs rehashing given current policy.
     */
    public static function needsRehash(string $hash): bool
    {
        return Hash::needsRehash($hash);
    }

    /**
     * Get the minimum length requirement.
     */
    public static function getMinLength(): int
    {
        return self::$policy['min_length'] ?? 8;
    }
}
