<?php
declare(strict_types=1);

namespace App\CLI;

use App\CLI\Command;

/**
 * senzbuild new <project-name>
 *
 * Creates a new project scaffold from this framework in the current directory.
 * Generates a .env from .env.example, sets up composer.json with framework
 * as a dependency, and creates the standard project directory structure.
 */
class SenzBuildNew extends Command
{
    protected array $defaultFiles = [
        'public/index.php' => '<?php
declare(strict_types=1);

require __DIR__ . "/../vendor/autoload.php";

use App\Core\Config;
use App\Core\Container;
use App\Core\Router;
use App\Core\Response;

// Load environment
$dotenvPath = dirname(__DIR__) . "/.env";
if (file_exists($dotenvPath) && class_exists("\\Dotenv\\Dotenv")) {
    $dotenv = \\Dotenv\\Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->safeLoad();
}

// Load config
Config::load(dirname(__DIR__) . "/config");

// Boot application
$app = new Container();
$app->register(\\App\\Providers\\CoreServiceProvider::class);
$app->register(\\App\\Providers\\DatabaseServiceProvider::class);

$router = new Router($app);
require dirname(__DIR__) . "/routes/web.php";

$request = new \\App\\Core\\Request();
$response = new Response();
$router->dispatch($request, $response);
',
        'composer.json' => '{
    "name": "senz/{PROJECT_NAME_LOWER}",
    "description": "A custom PHP API built with SENZ Framework",
    "type": "project",
    "require": {
        "php": ">=8.2",
        "vlucas/phpdotenv": "^5.6",
        "monolog/monolog": "^3.9",
        "firebase/php-jwt": "^7.0.4",
        "phpmailer/phpmailer": "^6.10",
        "guzzlehttp/psr7": "^2.12"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.5",
        "fakerphp/faker": "^1.24"
    },
    "autoload": {
        "psr-4": {
            "App\\\\": "src/",
            "Database\\\\": "Database/",
            "Jobs\\\\": "jobs/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\\\": "tests/"
        }
    },
    "scripts": {
        "serve": "php bin/server",
        "test": "vendor/bin/phpunit",
        "migrate": "php bin/console migrate"
    }
}
',
        'routes/web.php' => '<?php
// Application routes
$router->get("/api/v1/health", function ($request, $response) {
    return $response->jsonResponse([
        "success" => true,
        "status" => "healthy",
        "version" => "1.0.0"
    ]);
});

$router->get("/api/v1/ping", function ($request, $response) {
    return $response->jsonResponse([
        "success" => true,
        "status" => "ok",
        "message" => "pong"
    ]);
});
',
        'src/Controllers/Controller.php' => '<?php
declare(strict_types=1);

namespace App\\Controllers;

use App\\Core\\Controller as Base;

abstract class Controller extends Base
{
    // Base controller for all application controllers
}
',
    ];

    public function handle(array $args): void
    {
        // Handle help flag
        if (in_array('--help', $args, true) || in_array('-h', $args, true)) {
            $this->info('Usage: senzbuild new <project-name> [options]');
            $this->info('');
            $this->info('Creates a new project scaffold from this framework.');
            $this->info('');
            $this->info('Options:');
            $this->info('  --no-install    Skip composer install');
            $this->info('  -h, --help      Show this help message');
            $this->info('');
            $this->info('Example:');
            $this->info('  senzbuild new my-app');
            $this->info('  senzbuild new my-app --no-install');
            return;
        }

        $projectName = $args[0] ?? null;
        $skipComposer = in_array('--no-install', $args, true);

        if (!$projectName) {
            $this->error('Usage: senzbuild new <project-name>');
            $this->info('Example: senzbuild new my-app');
            return;
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $projectName)) {
            $this->error('Project name must contain only letters, numbers, hyphens, and underscores.');
            return;
        }

        $targetPath = getcwd() . '/' . $projectName;

        if (is_dir($targetPath)) {
            $this->error("Directory '{$projectName}' already exists.");
            return;
        }

        $this->info("Creating project '{$projectName}'...");

        // Validate working directory is writable
        $cwd = getcwd();
        if (!is_writable($cwd)) {
            $this->error("Current directory '{$cwd}' is not writable.");
            $this->info('Run from a project directory you have write access to.');
            return;
        }
        mkdir($targetPath, 0755, true);

        // Create directory structure
        $directories = [
            'src/Controllers',
            'src/Models',
            'src/Services',
            'src/Middleware',
            'Database/Migrations',
            'Database/Seeders',
            'jobs',
            'tests/Unit',
            'tests/Feature',
            'storage/logs',
            'storage/cache',
            'storage/sessions',
            'storage/uploads',
            'storage/backups',
        ];

        foreach ($directories as $dir) {
            mkdir($targetPath . '/' . $dir, 0755, true);
            $this->info("  Created {$dir}/");
        }

        // Write default files
        foreach ($this->defaultFiles as $relativePath => $content) {
            $fullPath = str_replace('{PROJECT_NAME}', $projectName, $targetPath . '/' . $relativePath);
            $fullPath = str_replace('{PROJECT_NAME_LOWER}', strtolower($projectName), $fullPath);
            $dir = dirname($fullPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            // Replace {PROJECT_NAME} and {PROJECT_NAME_LOWER} placeholders in file content
            $content = str_replace('{PROJECT_NAME}', $projectName, $content);
            $content = str_replace('{PROJECT_NAME_LOWER}', strtolower($projectName), $content);
            file_put_contents($fullPath, $content);
            $this->info("  Created {$relativePath}");
        }

        // Copy .env.example and create .env
        $envExample = __DIR__ . '/../../.env.example';
        $envFile = $targetPath . '/.env';
        if (file_exists($envExample)) {
            $envContent = file_get_contents($envExample);
            // Customize for new project
            $envContent = str_replace('SENZ DIGITAL SOLUTIONS', strtoupper($projectName), $envContent);
            $envContent = preg_replace("/DB_NAME=[^\\s]*/", "DB_NAME=" . strtolower($projectName) . "_db", $envContent);
            file_put_contents($envFile, $envContent);
            $this->success("  Created .env (customized for {$projectName})");
        }

        // Copy framework template files
        $this->copyFrameworkFiles($targetPath);

        if (!$skipComposer) {
            $this->info('Running composer install...');
            chdir($targetPath);
            exec('composer install --no-interaction --prefer-dist', $output, $result);
            foreach ($output as $line) {
                echo "  {$line}\n";
            }
            if ($result === 0) {
                $this->success('Dependencies installed');
            } else {
                $this->warning('Composer install had issues. Run composer install manually.');
            }
            chdir(__DIR__ . '/../../');
        }

        $this->success("Project '{$projectName}' created at {$targetPath}");
        $this->info('');
        $this->info('Next steps:');
        $this->info("  cd {$projectName}");
        if ($skipComposer) {
            $this->info('  composer install');
        }
        $this->info('  php bin/console build:init --db');
        $this->info('  php bin/console migrate');
        $this->info('  php bin/console serve');
    }

    protected function copyFrameworkFiles(string $targetPath): void
    {
        $frameworkPath = __DIR__ . '/../../';
        $filesToCopy = [
            'bin/console' => 'bin/console',
            'bin/server' => 'bin/server',
            'config/app.php' => 'config/app.php',
            'config/database.php' => 'config/database.php',
            'config/api.php' => 'config/api.php',
            'config/queue.php' => 'config/queue.php',
            'Core/helpers.php' => 'helpers.php',
            'App/CLI/MakeController.php' => 'App/CLI/MakeController.php',
            'App/CLI/MakeModel.php' => 'App/CLI/MakeModel.php',
            'App/CLI/Migrate.php' => 'App/CLI/Migrate.php',
        ];

        foreach ($filesToCopy as $src => $dst) {
            $srcPath = $frameworkPath . '/' . $src;
            if (file_exists($srcPath)) {
                $dstPath = $targetPath . '/' . $dst;
                $dstDir = dirname($dstPath);
                if (!is_dir($dstDir)) {
                    mkdir($dstDir, 0755, true);
                }
                copy($srcPath, $dstPath);
            }
        }
    }
}
