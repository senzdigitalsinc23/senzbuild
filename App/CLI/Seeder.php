<?php
declare(strict_types=1);

namespace App\CLI;

use App\Core\Container;
use App\Services\Database\SeederRunner;

class Seeder extends Command
{
    public string $name = 'db:seed';
    public string $description = 'Seed the database with sample data';

    public function handle(array $args): void
    {
        $container = new Container();
        $db = $container->resolve(\PDO::class);
        $seedersPath = dirname(__DIR__, 2) . '/Database/Seeders';

        $runner = new SeederRunner($db, $seedersPath);

        $seederClass = $args[0] ?? null;

        try {
            $this->info("Running database seeders...");
            $runner->seed($seederClass);
            $this->success("Database seeding completed successfully.");
        } catch (\Throwable $e) {
            $this->error("Seeding failed: " . $e->getMessage());
        }
    }
}
