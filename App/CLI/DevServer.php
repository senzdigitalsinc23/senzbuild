<?php
declare(strict_types=1);
namespace App\CLI;

class DevServer extends Command
{
    public function handle(array $args): void
    {
        $host = $args[0] ?? '127.0.0.1';
        $port = $args[1] ?? '8000';

        $docRoot = realpath(__DIR__ . '/../../public');

        $this->info("Starting PHP built-in server at http://{$host}:{$port}");
        $this->info("Document root: {$docRoot}");

        // Build command string
        $cmd = sprintf(
            'php -S %s:%s -t %s',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($docRoot)
        );

        // Run server (blocking)
        passthru($cmd);
    }

    
}
