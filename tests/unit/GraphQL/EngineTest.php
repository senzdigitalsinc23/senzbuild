<?php
declare(strict_types=1);

namespace Tests\Unit\GraphQL;

use PHPUnit\Framework\TestCase;
use App\GraphQL\Engine;
use App\GraphQL\Field;
use App\GraphQL\Type;
use App\GraphQL\Schema;
use App\GraphQL\Arg;

class EngineTest extends TestCase
{
    private Engine $engine;

    protected function setUp(): void
    {
        $schema = new Schema('Query');

        $schema->addType(new Type('Query')
            ->field('hello', new Field('String', fn() => 'world'))
            ->field('user', (new Field('User', fn($args) => [
                'id'    => $args['id'] ?? null,
                'name'  => 'Test User',
                'email' => 'test@example.com',
            ]))->args(new Arg('id', 'ID', required: true)))
            ->field('users', new Field('[User]', fn() => [
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
            ]))
            ->field('count', new Field('Int', fn() => 42))
            ->field('active', new Field('Boolean', fn() => true))
        );

        $schema->addType(new Type('User')
            ->field('id',   new Field('ID'))
            ->field('name', new Field('String'))
            ->field('email', new Field('String'))
        );

        $this->engine = new Engine($schema);
    }

    protected function makeRequest(string $query, array $variables = []): \App\Core\Request
    {
        // Wrap bare selection sets in a query operation
        $normalizedQuery = trim($query);
        if (!str_starts_with($normalizedQuery, 'query') && !str_starts_with($normalizedQuery, '{')) {
            $normalizedQuery = '{' . $normalizedQuery . '}';
        }
        // Ensure it starts with "query" if it's a bare selection set
        if (str_starts_with($normalizedQuery, '{')) {
            $normalizedQuery = 'query ' . $normalizedQuery;
        }

        $payload = json_encode(['query' => $normalizedQuery, 'variables' => $variables]);

        $_SERVER['REQUEST_METHOD']  = 'POST';
        $_SERVER['REQUEST_URI']     = '/api/v1/graphql';
        $_SERVER['REMOTE_ADDR']     = '127.0.0.1';
        $_SERVER['CONTENT_TYPE']    = 'application/json';
        $_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';
        $_SERVER['CONTENT_LENGTH']  = (string)strlen($payload);

        $request = new \App\Core\Request();
        $ref = new \ReflectionClass($request);
        $prop = $ref->getProperty('bodyParams');
        $prop->setAccessible(true);
        $prop->setValue($request, ['query' => $normalizedQuery, 'variables' => $variables]);

        return $request;
    }

    public function test_simple_query(): void
    {
        $request  = $this->makeRequest('{ hello }');
        $response = new \App\Core\Response();

        $result = $this->engine->execute($request, $response);
        $data   = json_decode($result->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertSame('world', $data['data']['hello']);
    }

    public function test_query_with_arguments(): void
    {
        $request  = $this->makeRequest('{ user(id: 1) { id name } }');
        $response = new \App\Core\Response();

        $result = $this->engine->execute($request, $response);
        $data   = json_decode($result->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertSame(1, $data['data']['user']['id']);
        $this->assertSame('Test User', $data['data']['user']['name']);
    }

    public function test_query_with_variables(): void
    {
        $request  = $this->makeRequest(
            'query($userId: ID!) { user(id: $userId) { name } }',
            ['userId' => 99]
        );
        $response = new \App\Core\Response();

        $result = $this->engine->execute($request, $response);
        $data   = json_decode($result->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertSame('Test User', $data['data']['user']['name']);
    }

    public function test_list_query(): void
    {
        $request  = $this->makeRequest('{ users { id name } }');
        $response = new \App\Core\Response();

        $result = $this->engine->execute($request, $response);
        $data   = json_decode($result->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['data']['users']);
        $this->assertSame('Alice', $data['data']['users'][0]['name']);
    }

    public function test_scalar_types(): void
    {
        $request  = $this->makeRequest('{ count active }');
        $response = new \App\Core\Response();

        $result = $this->engine->execute($request, $response);
        $data   = json_decode($result->getContent(), true);

        $this->assertSame(42, $data['data']['count']);
        $this->assertTrue($data['data']['active']);
    }

    public function test_nested_selection(): void
    {
        $request  = $this->makeRequest('{ user(id: 5) { id name email } }');
        $response = new \App\Core\Response();

        $result = $this->engine->execute($request, $response);
        $data   = json_decode($result->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertSame('test@example.com', $data['data']['user']['email']);
    }

    public function test_missing_required_argument(): void
    {
        $request  = $this->makeRequest('{ user { id } }');
        $response = new \App\Core\Response();

        $result = $this->engine->execute($request, $response);
        $data   = json_decode($result->getContent(), true);

        $this->assertFalse($data['success'] ?? true);
    }

    public function test_empty_query_returns_error(): void
    {
        $request  = $this->makeRequest('', []);
        // Force empty query by bypassing normalization
        $ref = new \ReflectionClass($request);
        $prop = $ref->getProperty('bodyParams');
        $prop->setAccessible(true);
        $prop->setValue($request, ['query' => '', 'variables' => []]);
        $response = new \App\Core\Response();

        $result = $this->engine->execute($request, $response);
        $data   = json_decode($result->getContent(), true);

        $this->assertFalse($data['success'] ?? true);
    }

    public function test_list_of_scalars(): void
    {
        $schema = new Schema('Query');
        $schema->addType(new Type('Query')
            ->field('tags', new Field('[String]', fn() => ['a', 'b', 'c']))
        );

        $engine  = new Engine($schema);
        $request = $this->makeRequest('{ tags }');
        $response = new \App\Core\Response();

        $result = $engine->execute($request, $response);
        $data   = json_decode($result->getContent(), true);

        $this->assertSame(['a', 'b', 'c'], $data['data']['tags']);
    }
}
