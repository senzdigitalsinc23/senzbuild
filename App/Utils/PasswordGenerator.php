<?php
declare(strict_types=1);

namespace App\Utils;

/**
 * Secure password generator utility.
 * 
 * Generates cryptographically secure random passwords that meet
 * modern security standards.
 */
class PasswordGenerator
{
    /**
     * Generate a secure random password.
     *
     * @param int $length Password length (minimum 12 characters recommended)
     * @param bool $includeSymbols Whether to include special characters
     * @return string The generated password
     * @throws \Exception If secure random generation fails
     */
    public static function generate(int $length = 12, bool $includeSymbols = true): string
    {
        if ($length < 8) {
            throw new \InvalidArgumentException('Password length must be at least 8 characters');
        }

        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $symbols = '!@#$%^&*()_+-=[]{}|;:,.<>?';

        // Build character pool
        $pool = $uppercase . $lowercase . $numbers;
        if ($includeSymbols) {
            $pool .= $symbols;
        }

        $poolLength = strlen($pool);
        $password = '';

        // Ensure at least one character from each required set
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        
        if ($includeSymbols) {
            $password .= $symbols[random_int(0, strlen($symbols) - 1)];
        }

        // Fill remaining length with random characters
        $remainingLength = $length - strlen($password);
        for ($i = 0; $i < $remainingLength; $i++) {
            $password .= $pool[random_int(0, $poolLength - 1)];
        }

        // Shuffle the password to randomize character positions
        $passwordArray = str_split($password);
        shuffle($passwordArray);
        
        return implode('', $passwordArray);
    }

    /**
     * Generate a memorable but secure password using word combinations.
     *
     * @param int $wordCount Number of words to use (minimum 3)
     * @param bool $includeNumbers Whether to include numbers
     * @return string The generated password
     */
    public static function generateMemorable(int $wordCount = 4, bool $includeNumbers = true): string
    {
        if ($wordCount < 3) {
            throw new \InvalidArgumentException('Word count must be at least 3');
        }

        // Common word list (you can expand this)
        $words = [
            'Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo', 'Foxtrot',
            'Golf', 'Hotel', 'India', 'Juliet', 'Kilo', 'Lima',
            'Mike', 'November', 'Oscar', 'Papa', 'Quebec', 'Romeo',
            'Sierra', 'Tango', 'Uniform', 'Victor', 'Whiskey', 'Xray',
            'Yankee', 'Zulu', 'Tiger', 'Lion', 'Eagle', 'Falcon',
            'Phoenix', 'Dragon', 'Wolf', 'Bear', 'Hawk', 'Panther'
        ];

        $selectedWords = [];
        $wordListSize = count($words);

        for ($i = 0; $i < $wordCount; $i++) {
            $selectedWords[] = $words[random_int(0, $wordListSize - 1)];
        }

        $password = implode('', $selectedWords);

        if ($includeNumbers) {
            $password .= random_int(100, 999);
        }

        // Add a special character
        $symbols = '!@#$%^&*';
        $password .= $symbols[random_int(0, strlen($symbols) - 1)];

        return $password;
    }

    /**
     * Validate password strength.
     *
     * @param string $password The password to validate
     * @return array Validation result with 'valid' boolean and 'errors' array
     */
    public static function validateStrength(string $password): array
    {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }

        // Check for common weak passwords
        $weakPasswords = [
            'password', 'password123', '12345678', 'qwerty', 'abc123',
            'letmein', 'welcome', 'monkey', '1234567890', 'admin'
        ];

        if (in_array(strtolower($password), $weakPasswords)) {
            $errors[] = 'Password is too common and easily guessable';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'strength' => self::calculateStrength($password)
        ];
    }

    /**
     * Calculate password strength score (0-100).
     *
     * @param string $password The password to evaluate
     * @return int Strength score
     */
    private static function calculateStrength(string $password): int
    {
        $score = 0;

        // Length score (up to 40 points)
        $length = strlen($password);
        $score += min(40, $length * 2);

        // Character variety (up to 60 points)
        if (preg_match('/[a-z]/', $password)) $score += 10;
        if (preg_match('/[A-Z]/', $password)) $score += 10;
        if (preg_match('/[0-9]/', $password)) $score += 10;
        if (preg_match('/[^A-Za-z0-9]/', $password)) $score += 15;

        // Bonus for mixing character types
        $types = 0;
        if (preg_match('/[a-z]/', $password)) $types++;
        if (preg_match('/[A-Z]/', $password)) $types++;
        if (preg_match('/[0-9]/', $password)) $types++;
        if (preg_match('/[^A-Za-z0-9]/', $password)) $types++;

        if ($types >= 3) $score += 10;
        if ($types == 4) $score += 5;

        return min(100, $score);
    }
}
