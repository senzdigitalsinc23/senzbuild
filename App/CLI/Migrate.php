<?php
declare(strict_types=1);

namespace App\CLI;

use App\Core\Container;
use Database\Migrator;

class Migrate extends Command
{
    public string $name = 'migrate';
    public string $description = 'Run pending database migrations';

    public function handle(array $args): void
    {
        $container = new Container();
        // Note: In a real app, the container would be shared.
        // Since bin/console creates a new one, we need the DB connection.
        $db = $container->resolve(\PDO::class);
        $migrationsPath = dirname(__DIR__, 2) . '/Database/Migrations';

        $migrator = new Migrator($db, $migrationsPath);

        $action = $args[0] ?? 'migrate';

        if ($action === 'migrate') {
            $this->info("Starting migrations...");
            $migrator->migrate();
            $this->success("Migrations completed.");
        } elseif ($action === 'rollback') {
            $this->info("Starting rollback...");
            $migrator->rollback();
            $this->success("Rollback completed.");
        } else {
            $this->error("Unknown action: {$action}. Use 'migrate' or 'rollback'.");
        }
    }
}
