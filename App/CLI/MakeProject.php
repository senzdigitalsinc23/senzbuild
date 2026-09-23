<?php
declare(strict_types=1);

namespace App\CLI;

class MakeProject extends Command
{
    public string $name = 'make:project';
    public string $description = 'Scaffold a new API project skeleton';

    public function handle(array $args): void
    {
        $projectDir = $args[0] ?? getcwd() . '/new-api-project';

        if (is_dir($projectDir)) {
            echo "Error: Directory '{$projectDir}' already exists.\n";
            exit(1);
        }

        echo "Creating project skeleton at: {$projectDir}\n";

        $structure = [
            'App/Controllers/Api',
            'App/Controllers/Web',
            'App/Models',
            'App/DTOs',
            'App/Services',
            'App/Repositories',
            'App/Middleware',
            'App/Exceptions',
            'App/CLI',
            'App/Utils',
            'App/Templates/Email',
            'Config',
            'Database/Migrations',
            'Database/Seeders',
            'Routes',
            'Public',
            'Storage/Logs',
            'Storage/Cache',
            'Storage/Files',
            'Storage/Jobs',
            'Tests/Unit',
            'Tests/Feature',
            'Resources/Views',
            'bin',
        ];

        foreach ($structure as $dir) {
            $path = $projectDir . '/' . $dir;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
                echo "  Created: {$dir}\n";
            }
        }

        // Create base files
        $this->createComposerJson($projectDir);
        $this->createEnvExample($projectDir);
        $this->createIndexPhp($projectDir);
        $this->createRoutesApi($projectDir);
        $this->createRoutesWeb($projectDir);
        $this->createConfigApp($projectDir);
        $this->createConfigDatabase($projectDir);
        $this->createHealthController($projectDir);
        $this->createUserModel($projectDir);
        $this->createGitIgnore($projectDir);
        $this->createConsoleScript($projectDir);
        $this->createSampleService($projectDir);


        echo "\nProject skeleton created successfully!\n";
        echo "Next steps:\n";
        echo "  1. cd {$projectDir}\n";
        echo "  2. composer install\n";
        echo "  3. cp .env.example .env and configure your database\n";
        echo "  4. Run 'php cli.php serve' to start the development server\n";
    }

    private function createComposerJson(string $dir): void
    {
        $content = json_encode([
            'name' => 'app/api-project',
            'description' => 'API project built on the framework',
            'type' => 'project',
            'require' => [
                'php' => '>=8.2',
                'senzdigitals/framework' => '^1.0',
            ],
            'autoload' => [
                'psr-4' => [
                    'App\\' => 'App/',
                ],
            ],
            'autoload-dev' => [
                'psr-4' => [
                    'Tests\\' => 'tests/',
                ],
            ],
            'scripts' => [
                'serve' => 'php bin/console serve',
                'test' => 'vendor/bin/phpunit',
                'swagger' => 'php bin/console make:swagger',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        file_put_contents($dir . '/composer.json', $content);
        echo "  Created: composer.json\n";
    }

    private function createEnvExample(string $dir): void
    {
        $content = <<<ENV
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_HOST=127.0.0.1
DB_NAME=api_project_db
DB_USER=root
DB_PASS=

CORS_ALLOWED_ORIGINS=http://localhost:3000

LOG_FORMAT=json
ENV;
        file_put_contents($dir . '/.env.example', $content);
        echo "  Created: .env.example\n";
    }

    private function createIndexPhp(string $dir): void
    {
        $content = <<<'PHP'
<?php

require __DIR__ . '/../vendor/autoload.php';

$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->safeLoad();
}

use App\Core\Container;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;

Config::load(dirname(__DIR__) . '/config');

$container = new Container();
$container->singleton(Request::class, fn() => new Request());
$container->singleton(Response::class, fn() => new Response());

$router = new Router($container);
require __DIR__ . '/../routes/api.php';
require __DIR__ . '/../routes/web.php';

$response = $router->dispatch(
    $container->resolve(Request::class),
    $container->resolve(Response::class)
);

if (!headers_sent()) {
    $response->send();
}
PHP;
        file_put_contents($dir . '/public/index.php', $content);
        echo "  Created: public/index.php\n";
    }

    private function createRoutesApi(string $dir): void
    {
        $content = <<<'PHP'
<?php

use App\Controllers\Api\HealthController;

$router->getApi('v1', '/health', [HealthController::class, 'check']);
$router->getApi('v1', '/ping', [HealthController::class, 'ping']);
PHP;
        file_put_contents($dir . '/routes/api.php', $content);
        echo "  Created: routes/api.php\n";
    }

    private function createRoutesWeb(string $dir): void
    {
        $content = <<<'PHP'
<?php

$router->get('/', [App\Controllers\HomeController::class, 'index']);
PHP;
        file_put_contents($dir . '/routes/web.php', $content);
        echo "  Created: routes/web.php\n";
    }

    private function createConfigApp(string $dir): void
    {
        $content = <<<'PHP'
<?php

return [
    'name' => env('APP_NAME', 'API Project'),
    'env' => env('APP_ENV', 'production'),
    'debug' => env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost:8000'),
];
PHP;
        file_put_contents($dir . '/config/app.php', $content);
        echo "  Created: config/app.php\n";
    }

    private function createConfigDatabase(string $dir): void
    {
        $content = <<<'PHP'
<?php

return [
    'driver' => env('DB_DRIVER', 'mysql'),
    'host' => env('DB_HOST', '127.0.0.1'),
    'dbname' => env('DB_NAME', 'database'),
    'username' => env('DB_USER', 'root'),
    'password' => env('DB_PASS', ''),
    'charset' => 'utf8mb4',
];
PHP;
        file_put_contents($dir . '/config/database.php', $content);
        echo "  Created: config/database.php\n";
    }

    private function createHealthController(string $dir): void
    {
        $content = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Response;

class HealthController extends Controller
{
    public function check(): string
    {
        header('Content-Type: application/json');
        return json_encode([
            'status' => 'healthy',
            'timestamp' => date('c'),
            'version' => '1.0.0',
        ]);
    }

    public function ping(): string
    {
        header('Content-Type: application/json');
        return json_encode([
            'status' => 'ok',
            'timestamp' => date('c'),
            'message' => 'Application is running',
        ]);
    }
}
PHP;
        $path = $dir . '/App/Controllers/Api/HealthController.php';
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, $content);
        echo "  Created: App/Controllers/Api/HealthController.php\n";
    }

    private function createUserModel(string $dir): void
    {
        $content = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Models;

use Database\ORM\Model;

class User extends Model
{
    protected static string $table = 'users';
}
PHP;
        $path = $dir . '/App/Models/User.php';
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, $content);
        echo "  Created: App/Models/User.php\n";
    }

    private function createGitIgnore(string $dir): void
    {
        $content = <<<'TXT'
/vendor/
.env
storage/logs/*.log
storage/cache/*.cache
storage/files/*
!storage/files/.gitignore
.phpunit.result.cache
.idea/
.vscode/
TXT;
        file_put_contents($dir . '/.gitignore', $content);
        echo "  Created: .gitignore\n";
    }

    private function createConsoleScript(string $dir): void
    {
        $content = <<<'PHP'
<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Container;
use App\CLI\Command;

// Simple console app runner
$container = new Container();
$commandName = $argv[1] ?? 'help';

// This is a simplified version of the command registry
$commands = [
    'make:project' => \App\CLI\MakeProject::class,
    'make:swagger' => \App\CLI\MakeSwagger::class,
    // ... add others
];

if (!isset($commands[$commandName])) {
    echo "Unknown command: $commandName\n";
    exit(1);
}

$command = $container->resolve($commands[$commandName]);
$command->handle(array_slice($argv, 2));
PHP;
        file_put_contents($dir . '/bin/console', $content);
        echo "  Created: bin/console\n";
    }

    private function createSampleService(string $dir): void
    {
        $content = <<<'PHP'
<?php
declare(strict_types=1);

namespace App\Services;

class UserSmsService
{
    public function sendWelcomeSms(int $userId, string $phone): bool
    {
        // Imagine an SMS gateway integration here
        error_log("Sending welcome SMS to user {$userId} at {$phone}");
        return true;
    }
}
PHP;
        $path = $dir . '/App/Services/UserSmsService.php';
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, $content);
        echo "  Created: App/Services/UserSmsService.php\n";
    }
}