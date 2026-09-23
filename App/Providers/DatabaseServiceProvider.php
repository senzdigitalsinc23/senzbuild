<?php
declare(strict_types=1);

namespace App\Providers;

use App\Core\Container;
use App\Core\Interfaces\ServiceProviderInterface;
use App\Services\Database\MigrationRunner;
use App\Services\Database\SeederRunner;
use PDO;

class DatabaseServiceProvider implements ServiceProviderInterface
{
    public function register(Container $app): void
    {
        $app->singleton(PDO::class, function($app) {
            $driver  = \App\Core\Config::get('database.driver', 'mysql');
            $host    = \App\Core\Config::get('database.host', '127.0.0.1');
            $dbname  = \App\Core\Config::get('database.dbname', 'api_project_db');
            $user    = \App\Core\Config::get('database.username', 'root');
            $pass    = \App\Core\Config::get('database.password', '');
            $charset = \App\Core\Config::get('database.charset', 'utf8mb4');

            switch ($driver) {
                case 'sqlite':
                    $dsn = "sqlite:$dbname";
                    break;
                case 'oci':
                    $dsn = "oci:dbname=$dbname";
                    break;
                case 'mysql':
                default:
                    $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
                    break;
            }

            return new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        });

        $app->singleton(MigrationRunner::class, function($app) {
            $db = $app->resolve(PDO::class);
            $path = dirname(__DIR__, 2) . '/Database/Migrations';
            return new MigrationRunner($db, $path);
        });

        $app->singleton(SeederRunner::class, function($app) {
            $db = $app->resolve(PDO::class);
            $path = dirname(__DIR__, 2) . '/Database/Seeders';
            return new SeederRunner($db, $path);
        });
    }
}
