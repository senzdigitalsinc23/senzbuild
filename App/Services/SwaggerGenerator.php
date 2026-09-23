<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use OpenApi\Generator;

/**
 * Service for generating OpenAPI (Swagger) specifications
 */
class SwaggerGenerator
{
    /**
     * Scans defined directories and generates OpenAPI JSON
     *
     * @return string JSON representation of the OpenAPI spec
     */
    public function generate(): string
    {
        $paths = Config::get('swagger.scan_paths', [
            realpath(dirname(__DIR__, 2) . '/App/Controllers'),
            realpath(dirname(__DIR__, 2) . '/App/Models'),
            realpath(dirname(__DIR__, 2) . '/App/DTOs'),
            realpath(dirname(__DIR__, 2) . '/routes'),
        ]);

        $dirs = array_filter($paths, fn($d) => $d !== false && is_dir($d));

        $openapi = Generator::scan(array_values($dirs));
        return $openapi->toJson();
    }

    /**
     * Saves the generated JSON to a specific file path
     *
     * @param string $path Path to save the JSON file
     * @return bool Success status
     */
    public function saveToFile(string $path): bool
    {
        $json = $this->generate();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return (bool)file_put_contents($path, $json);
    }
}
