<?php
declare(strict_types=1);

namespace App\CLI;

class MakeMigration extends Command
{
    public string $name = 'make:migration';
    public string $description = 'Generate a new database migration file';

    public function handle(array $args): void
    {
        $migrationName = $args[0] ?? null;

        if (!$migrationName) {
            $this->error("Please provide a migration name. Usage: php bin/console make:migration CreateUsersTable");
            return;
        }

        $timestamp = date('YmdHis');
        $className = $this->formatMigrationClassName($migrationName, $timestamp);
        $fileName = "{$timestamp}_" . $this->toSnakeCase($migrationName) . ".php";
        $filePath = __DIR__ . "/../../Database/Migrations/{$fileName}";

        $stub = <<<PHP
<?php
declare(strict_types=1);

use Database\Migration;
use Database\ORM\SchemaBuilder;

class {$className} extends Migration
{
    public function up(): void
    {
        \$this->schema->create('your_table_name', function(SchemaBuilder \$schema) {
            \$schema->id();
            \$schema->string('name', 255)->nullable(false);
            \$schema->timestamps();
        });
    }

    public function down(): void
    {
        \$this->schema->dropIfExists('your_table_name');
    }
}
PHP;

        if (file_put_contents($filePath, $stub)) {
            $this->success("Migration created successfully: {$fileName}");
        } else {
            $this->error("Failed to create migration file.");
        }
    }

    protected function formatMigrationClassName(string $name, string $timestamp): string
    {
        $name = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));
        return "{$name}{$timestamp}";
    }

    private function toSnakeCase(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }
}
