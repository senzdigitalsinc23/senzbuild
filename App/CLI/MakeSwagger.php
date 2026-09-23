<?php
declare(strict_types=1);

namespace App\CLI;

use App\Services\SwaggerGenerator;
use App\Core\Config;

class MakeSwagger extends Command
{
    public string $name = 'make:swagger';
    public string $description = 'Generate OpenAPI documentation JSON file';

    public function handle(array $args): void
    {
        echo "Generating OpenAPI specification...\n";

        $generator = new SwaggerGenerator();
        $cachePath = Config::get('swagger.cache_path', dirname(__DIR__, 3) . '/storage/cache/openapi.json');

        if ($generator->saveToFile($cachePath)) {
            echo "Successfully exported swagger.json to: {$cachePath}\n";
        } else {
            echo "Error: Failed to write swagger.json to disk.\n";
            exit(1);
        }
    }
}
