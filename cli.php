<?php

/**
 * CLI entry point for the Multishop Manager scheduler and other commands.
 *
 * Usage:
 *   php cli.php schedule:run              (daemon mode — loops every 60s)
 *   php cli.php schedule:run --once       (single check)
 *   php cli.php schedule:run --once --force  (force-run all tasks now)
 *   php cli.php backup:run                (run backup immediately)
 *   php cli.php queue:work                (process queue jobs)
 *   php cli.php migrate                   (run migrations)
 */

// Bootstrap autoloader
require __DIR__ . '/vendor/autoload.php';

// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

$app = new \App\CLI\App($argv);
$app->run();
