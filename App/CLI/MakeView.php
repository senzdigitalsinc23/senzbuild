<?php
declare(strict_types=1);
namespace App\CLI;

class MakeView extends Command
{
    public function handle(array $args): void
    {
        $name = $args[0] ?? null;
        $directory = $args[1] ?? null;

        if (!$name) {
            $this->error("Please provide a model name.");
            return;
        }

        $filename = '';
        if ($directory) {
            $filename = __DIR__ . "/../Views/{$directory}/{$name}.view.php";
        }else {
            $filename = __DIR__ . "/../Views/{$name}.view.php";
        }

        if (file_exists($filename)) {
            $this->error("{$name} View already exists.");
            return;
        }

        $name = ucfirst($name);

        $template = <<<PHP
        <h2>$name View</h2>
        <div>Welcome to the $name view </div>

        PHP;

        //show($filename);

        file_put_contents($filename, $template);
        $this->info("{$name} View created successfully.");
    }

    private function toSnakeCase(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }

    
}
