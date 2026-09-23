<?php
declare(strict_types=1);

namespace App\Providers;

use App\Core\Container;
use App\Core\Interfaces\ServiceProviderInterface;
use App\Core\Cache;
use App\Core\Request;
use App\Core\Response;
use App\Core\Storage;
use App\Core\Queue;
use App\Core\EventDispatcher;
use App\Core\Health\HealthService;
use App\Core\Health\DatabaseHealthCheck;
use App\Core\Health\CacheHealthCheck;
use App\Core\Health\DiskSpaceHealthCheck;
use App\Core\Health\PhpVersionHealthCheck;

class CoreServiceProvider implements ServiceProviderInterface
{
    public function register(Container $app): void
    {
        $app->singleton(Cache::class, fn($app) => new Cache());
        $app->singleton(Request::class, fn($app) => new Request());
        $app->singleton(Response::class, fn($app) => new Response());

        $app->singleton(Storage::class, function ($app) {
            return new Storage(__DIR__ . '/../../storage/files');
        });

        $app->singleton(Queue::class, function ($app) {
            return new Queue(__DIR__ . '/../../storage/jobs');
        });

        $app->singleton(EventDispatcher::class, function ($app) {
            $queue = $app->resolve(Queue::class);
            return new EventDispatcher($queue);
        });

        // Register HealthService — database check resolves PDO lazily on first check() call
        $app->singleton(HealthService::class, function ($app) {
            $health = new HealthService();
            $health->registerCheck(new DatabaseHealthCheck($app));
            $health->registerCheck(new CacheHealthCheck($app->resolve(Cache::class)));
            $health->registerCheck(new DiskSpaceHealthCheck());
            $health->registerCheck(new PhpVersionHealthCheck());
            return $health;
        });

        $app->singleton(\App\Core\View::class, fn($app) => new \App\Core\View());
    }
}
