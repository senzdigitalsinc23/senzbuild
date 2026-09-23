<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Validates environment configuration on application startup
 * 
 * Ensures all required environment variables are set and valid
 * before the application starts processing requests.
 */
class EnvironmentValidator
{
    /**
     * Required environment variables for all environments
     */
    private array $required = [
        'APP_NAME',
        'APP_ENV',
        'APP_URL',
        'DB_HOST',
        'DB_NAME',
        'DB_USER',
        'JWT_SECRET',
    ];

    /**
     * Required environment variables for production only
     */
    private array $requiredInProduction = [
        'MAIL_HOST',
        'MAIL_PORT',
        'MAIL_USER',
        'MAIL_PASS',
        'SUPPORT_EMAIL',
    ];

    /**
     * Validate environment configuration
     *
     * @return array Validation result with errors and warnings
     */
    public function validate(): array
    {
        $errors = [];
        $warnings = [];

        // Check required variables
        foreach ($this->required as $var) {
            if (empty($_ENV[$var])) {
                $errors[] = "Missing required environment variable: {$var}";
            }
        }

        // Check JWT_SECRET strength
        if (!empty($_ENV['JWT_SECRET'])) {
            if (strlen($_ENV['JWT_SECRET']) < 32) {
                $warnings[] = "JWT_SECRET should be at least 32 characters for security";
            }
            
            // Check for default/weak secrets
            $weakSecrets = [
                'your-secret-key-change-this',
                'change_me',
                'secret',
                'jwt_secret',
                '12345678901234567890123456789012'
            ];
            
            if (in_array($_ENV['JWT_SECRET'], $weakSecrets)) {
                $errors[] = "JWT_SECRET is using a default/weak value. Please generate a strong secret.";
            }
        }

        // Check production requirements
        if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
            foreach ($this->requiredInProduction as $var) {
                if (empty($_ENV[$var])) {
                    $warnings[] = "Missing recommended variable for production: {$var}";
                }
            }

            // Check debug mode
            if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
                $warnings[] = "APP_DEBUG should be 'false' in production";
            }

            // Check if using localhost URLs
            if (isset($_ENV['APP_URL']) && strpos($_ENV['APP_URL'], 'localhost') !== false) {
                $warnings[] = "APP_URL contains 'localhost' in production environment";
            }
        }

        // Check database configuration
        if (!empty($_ENV['DB_HOST']) && $_ENV['DB_HOST'] === 'localhost') {
            $warnings[] = "Consider using '127.0.0.1' instead of 'localhost' for DB_HOST";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * Validate environment or throw exception
     *
     * @throws \RuntimeException If validation fails
     * @return void
     */
    public function validateOrFail(): void
    {
        $result = $this->validate();

        if (!$result['valid']) {
            $message = "\n=== Environment Configuration Errors ===\n\n";
            foreach ($result['errors'] as $error) {
                $message .= "  ❌ {$error}\n";
            }
            $message .= "\nPlease check your .env file and ensure all required variables are set.\n";
            $message .= "See .env.example for reference.\n\n";
            
            throw new \RuntimeException($message);
        }

        // Log warnings
        if (!empty($result['warnings'])) {
            foreach ($result['warnings'] as $warning) {
                error_log("⚠️  WARNING: {$warning}");
            }
        }
    }

    /**
     * Get list of required environment variables
     *
     * @return array
     */
    public function getRequiredVariables(): array
    {
        return $this->required;
    }

    /**
     * Get list of production-required environment variables
     *
     * @return array
     */
    public function getProductionRequiredVariables(): array
    {
        return $this->requiredInProduction;
    }
}
