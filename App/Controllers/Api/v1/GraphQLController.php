<?php
declare(strict_types=1);

namespace App\GraphQL;

use App\Core\Request;
use App\Core\Response;

/**
 * GraphQL controller — handles POST /api/v1/graphql requests.
 *
 * Schema is registered via the GraphQLServiceProvider.
 *
 * Example schema registration:
 *   $schema = new Schema('Query');
 *   $schema->addType(new Type('Query')
 *       ->field('hello', new Field('String', fn() => 'world'))
 *       ->field('user', new Field('User', fn($args) => ['id' => $args['id'], 'name' => 'Test']), [
 *           new Arg('id', 'ID', required: true),
 *       ])
 *   );
 *   $schema->addType(new Type('User')
 *       ->field('id',   new Field('ID'))
 *       ->field('name', new Field('String'))
 *   );
 *   $container->singleton(GraphQLSchema::class, fn() => $schema);
 */
class GraphQLController
{
    public function __construct(
        private readonly Engine $engine,
    ) {}

    /**
     * POST /api/v1/graphql
     */
    public function execute(Request $request, Response $response): Response
    {
        return $this->engine->execute($request, $response);
    }
}
