<?php
declare(strict_types=1);

namespace App\CLI;

class MakeModel extends Command
{
    public string $name = 'make:model';
    public string $description = 'Generate a Model and its corresponding Repository';

    public function handle(array $args): void
    {
        $name = $args[0] ?? null;

        if (!$name) {
            $this->error("Please provide a model name. Usage: php bin/console make:model Product");
            return;
        }

        $normalizedName = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));
        $tableName = $this->toSnakeCase($normalizedName) . 's';

        // 1. Create Model
        $modelPath = __DIR__ . "/../Models/{$normalizedName}.php";
        if (file_exists($modelPath)) {
            $this->warning("Model {$normalizedName} already exists, skipping...");
        } else {
            $modelTemplate = $this->getModelTemplate($normalizedName, $tableName);
            file_put_contents($modelPath, $modelTemplate);
            $this->success("Model {$normalizedName} created successfully.");
        }

        // 2. Create Repository
        $repoName = "{$normalizedName}Repository";
        $repoPath = __DIR__ . "/../Repositories/{$repoName}.php";
        if (file_exists($repoPath)) {
            $this->warning("Repository {$repoName} already exists, skipping...");
        } else {
            $repoTemplate = $this->getRepositoryTemplate($normalizedName, $repoName);
            file_put_contents($repoPath, $repoTemplate);
            $this->success("Repository {$repoName} created successfully.");
        }
    }

    private function getModelTemplate(string $name, string $table): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

namespace App\Models;

use Database\ORM\Model;

class {$name} extends Model
{
    protected static string \$table = '{$table}';

    // Define your properties here
    // public string \$name;
}
PHP;
    }

    private function getRepositoryTemplate(string $modelName, string $repoName): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use App\Core\Cache;
use App\Models\\{$modelName};

class {$repoName}
{
    protected PDO \$db;
    protected Cache \$cache;

    public function __construct(PDO \$db, Cache \$cache)
    {
        $this->db = \$db;
        $this->cache = \$cache;
    }

    public function find(int \$id): ?{$modelName}
    {
        // Implement find logic
        return null;
    }

    public function all(): array
    {
        // Implement all logic
        return [];
    }
}
PHP;
    }

    private function toSnakeCase(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }
}
