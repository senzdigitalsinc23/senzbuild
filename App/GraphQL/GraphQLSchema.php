<?php
declare(strict_types=1);

namespace App\GraphQL;

/**
 * Schema class — extend this and register in GraphQLServiceProvider.
 *
 * Example:
 *   class AppSchema extends GraphQLSchema
 *   {
 *       public function build(): Schema
 *       {
 *           $schema = new Schema('Query');
 *           $schema->addType(new Type('Query')
 *               ->field('user', new Field('User', [$this, 'resolveUser']), [
 *                   new Arg('id', 'ID!', required: true),
 *               ])
 *           );
 *           $schema->addType(new Type('User')
 *               ->field('id',    new Field('ID'))
 *               ->field('name',  new Field('String'))
 *               ->field('email', new Field('String'))
 *           );
 *           return $schema;
 *       }
 *
 *       public function resolveUser(array $args): ?array
 *       {
 *           return User::find((int)$args['id'])?->toArray();
 *       }
 *   }
 */
abstract class GraphQLSchema
{
    abstract public function build(): Schema;
}
