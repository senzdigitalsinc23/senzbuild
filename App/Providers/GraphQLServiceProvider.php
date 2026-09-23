<?php
declare(strict_types=1);

namespace App\Providers;

use App\Core\Cache;
use App\Core\Config;
use App\Core\Container;
use App\Core\Interfaces\ServiceProviderInterface;
use App\GraphQL\Engine;
use App\GraphQL\Field;
use App\GraphQL\Schema;
use App\GraphQL\Type;
use App\Controllers\Api\v1\GraphQLController;

/**
 * Service provider for the GraphQL layer.
 *
 * Registers the default schema and the GraphQL engine in the container.
 */
class GraphQLServiceProvider implements ServiceProviderInterface
{
    public function register(Container $app): void
    {
        $app->singleton(Engine::class, function (Container $app) {
            return $this->buildDefaultEngine();
        });

        $app->singleton(GraphQLController::class, function (Container $app) {
            return new GraphQLController(
                $app->resolve(Engine::class)
            );
        });
    }

    /**
     * Build the default engine with a basic schema.
     */
    protected function buildDefaultEngine(): Engine
    {
        $schema = new Schema('Query');

        $schema->addType(new Type('Query')
            ->field('hello', new Field('String', fn() => 'world'))
            ->field('health', new Field('HealthStatus', fn() => [
                'status'    => 'healthy',
                'timestamp' => date('c'),
            ]))
            ->field('version', new Field('String', fn() => Config::get('app.name', 'API Project')))
        );

        $schema->addType(new Type('HealthStatus')
            ->field('status', new Field('String'))
            ->field('timestamp', new Field('String'))
        );

        return new Engine($schema);
    }
}
