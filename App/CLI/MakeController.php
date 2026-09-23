<?php
declare(strict_types=1);

namespace App\CLI;

class MakeController extends Command
{
    public string $name = 'make:controller';
    public string $description = 'Generate a professional API controller';

    public function handle(array $args): void
    {
        $name = $args[0] ?? null;
        $directory = $args[1] ?? null;

        if (!$name) {
            $this->error("Please provide a controller name. Usage: php bin/console make:controller UserController [api/web]");
            return;
        }

        $normalizedName = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));
        $className = "{$normalizedName}Controller";

        $path = __DIR__ . '/../Controllers';
        if ($directory) {
            $path .= "/{$directory}";
        }

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $filename = "{$path}/{$className}.php";

        if (file_exists($filename)) {
            $this->error("Controller {$className} already exists.");
            return;
        }

        $namespace = "App\\Controllers";
        if ($directory) {
            $namespace .= "\\" . ucfirst($directory);
        }

        $template = <<<PHP
<?php
declare(strict_types=1);

namespace {$namespace};

use App\Core\Controller;
use App\Core\Response;
use App\Core\Request;

class {$className} extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request \$request, Response \$response): Response
    {
        return \$this->apiSuccess([
            'message' => 'List of {$normalizedName} retrieved'
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request \$request, Response \$response): Response
    {
        return \$this->apiSuccess([
            'message' => '{$normalizedName} created successfully'
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request \$request, Response \$response, int \$id): Response
    {
        return \$this->apiSuccess([
            'id' => \$id,
            'message' => '{$normalizedName} details retrieved'
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request \$request, Response \$response, int \$id): Response
    {
        return \$this->apiSuccess([
            'id' => \$id,
            'message' => '{$normalizedName} updated successfully'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request \$request, Response \$response, int \$id): Response
    {
        return \$this->apiSuccess([
            'id' => \$id,
            'message' => '{$normalizedName} deleted successfully'
        ]);
    }
}
PHP;

        file_put_contents($filename, $template);
        $this->success("Controller {$className} created successfully at {$filename}");
    }
}
