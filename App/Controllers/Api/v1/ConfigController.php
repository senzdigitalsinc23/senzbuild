<?php
declare(strict_types=1);

namespace App\Controllers\Api\v1;

use App\Core\Request;
use App\Core\Response;

/**
 * Returns application configuration and business settings
 */
class ConfigController
{
    /**
     * Returns the current application configuration
     */
    public function index(Request $request, Response $response): Response
    {
        $businessLevel = $this->getBusinessLevel();
        $businessName = $this->getBusinessName();
        $includesPharmacy = $this->getIncludesPharmacy();

        $data = [
            'business_level' => $businessLevel,
            'business_name' => $businessName,
            'includes_pharmacy' => $includesPharmacy,
        ];

        $response->setContent(json_encode([
            'success' => true,
            'data' => $data,
        ]));

        return $response;
    }

    private function getBusinessLevel(): string
    {
        $level = trim(strtolower($_ENV['BUSINESS_LEVEL'] ?? env('BUSINESS_LEVEL', 'starter')));

        $validLevels = ['starter', 'medium', 'advanced'];
        if (in_array($level, $validLevels, true)) {
            return $level;
        }

        return 'starter';
    }

    private function getBusinessName(): string
    {
        $name = trim($_ENV['BUSINESS_NAME'] ?? env('BUSINESS_NAME', 'API Project'));
        return $name === '' ? 'API Project' : $name;
    }

    private function getIncludesPharmacy(): bool
    {
        $value = $_ENV['INCLUDES_PHARMACY'] ?? env('INCLUDES_PHARMACY', 'true');

        $truthy = ['true', '1', 'yes', 'y', 't'];
        $falsy = ['false', '0', 'no', 'n', 'f'];

        $normalized = strtolower(trim((string)$value));
        if (in_array($normalized, $truthy, true)) {
            return true;
        }
        if (in_array($normalized, $falsy, true)) {
            return false;
        }

        return true;
    }
}
