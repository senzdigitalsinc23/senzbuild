<?php
declare(strict_types=1);

namespace App\CLI;

class MakeSeeder extends Command
{
    public function handle(array $args): void
    {
        $seederName = $args[0] ?? null;

        if (!$seederName) {
            $this->error("Please provide a seeder name. Usage: php cli.php make:seeder UserSeeder");
            return;
        }

        $className = $this->formatSeederClassName($seederName);
        $filePath = __DIR__ . "/../../Database/seeders/{$className}.php";

        $stub = "<?php\r\n\r\nnamespace Database\\\\Seeders;\r\n\r\nuse Database\\\\Seeder;\r\nuse PDO;\r\n\r\nclass {$className} extends Seeder\r\n{\r\n    public function run(): void\r\n    {\r\n        // Add your seeder logic here\r\n        // Example: \$this->execute(\\\"INSERT INTO users (name, email) VALUES (?, ?)\\\", [\\\'John Doe\\\', \\\'john@example.com\\\']);\r\n    }\r\n}\r\n";

        if (file_put_contents($filePath, $stub)) {
            $this->success("Seeder created successfully: {$className}.php");
        } else {
            $this->error("Failed to create seeder file.");
        }
    }

    protected function formatSeederClassName(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));
    }
}
