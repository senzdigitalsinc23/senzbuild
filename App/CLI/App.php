<?php
declare(strict_types=1);
namespace App\CLI;

class App
{
    protected array $argv;
    protected array $commands = [];

    public function __construct(array $argv)
    {
        $this->argv = $argv;

        // Register commands here
        $this->commands = [
            'migrate' => Migrate::class,
            'migrate:rollback' => Migrate::class,
            'make:migration' => MakeMigration::class,
            'make:model' => MakeModel::class,
            'make:controller' => MakeController::class,
            'make:view' => MakeView::class,
            'make:db' => MakeDb::class,
            'make:seeder' => MakeSeeder::class,
            'db:seed' => Seeder::class,
            'serve' => DevServer::class,
            'promote:students' => PromoteStudents::class,
            'scores:summarize' => SummarizeScores::class,
            'credits:expire' => ExpireCredits::class,
            'loyalty:process' => ProcessLoyalty::class,
            'queue:work' => QueueWorker::class,
            'schedule:run' => Scheduler::class,
            'backup:run' => BackupCommand::class,
            'make:project' => MakeProject::class,
        ];
    }

    public function run(): void
    {
        $commandName = $this->argv[1] ?? null;
        $args = array_slice($this->argv, 2);

        if (!$commandName || !isset($this->commands[$commandName])) {
            $this->printHelp();
            exit(1);
        }

        $commandClass = $this->commands[$commandName];
        $command = new $commandClass();
        $command->handle($args);
    }

    protected function printHelp(): void
    {
        echo "Usage:\n";
        echo "  php cli.php [command] [options]\n\n";
        echo "Available commands:\n";
        foreach (array_keys($this->commands) as $command) {
            echo "  - {$command}\n";
        }
    }
}
