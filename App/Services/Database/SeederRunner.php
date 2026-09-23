<?php
declare(strict_types=1);

namespace App\Services\Database;

use PDO;
use Exception;

class SeederRunner
{
    private PDO $db;
    private string $seedersPath;

    public function __construct(PDO $db, string $seedersPath)
    {
        $this->db = $db;
        $this->seedersPath = $seedersPath;
    }

    public function seed(?string $seederClass = null): void
    {
        if (!is_dir($this->seedersPath)) {
            throw new Exception("Seeders directory not found: {$this->seedersPath}");
        }

        $files = glob($this->seedersPath . '/*.php');
        if (empty($files)) {
            echo "No seeders found.\n";
            return;
        }

        sort($files);

        if ($seederClass) {
            $this->runSeeder($seederClass);
        } else {
            foreach ($files as $file) {
                $className = $this->deriveClassName($file);
                if ($className) {
                    $this->runSeeder($className);
                }
            }
        }
    }

    private function runSeeder(string $className): void
    {
        if (!class_exists($className)) {
            throw new Exception("Seeder class {$className} not found.");
        }

        $seeder = new $className($this->db);

        if (!method_exists($seeder, 'run')) {
            throw new Exception("Seeder {$className} has no run() method.");
        }

        $seeder->run();
    }

    private function deriveClassName(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        if (preg_match('/class\s+([a-zA-Z0-9_\\\-]+)\s+extends\s+Seeder/', $content, $matches)) {
            return $matches[1];
        }

        $filename = basename($filePath, '.php');
        return 'Database\\' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $filename)));
    }
}
