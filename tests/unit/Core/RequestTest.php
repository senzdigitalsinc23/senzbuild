<?php

namespace Tests\Unit\Core;

use App\Core\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(Request::class)]
class RequestTest extends TestCase
{
    private array $origServer;
    private array $origEnv;

    protected function setUp(): void
    {
        $this->origServer = $_SERVER;
        $this->origEnv = $_ENV;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->origServer;
        $_ENV = $this->origEnv;
    }

    private function createRequest(array $server = [], string $rawBody = ''): Request
    {
        $_SERVER = array_merge([
            'REQUEST_URI' => '/api/v1/test',
            'REQUEST_METHOD' => 'GET',
        ], $server);

        if ($rawBody !== '') {
            // Mock php://input via a stream wrapper isn't practical,
            // so we test bodyParams separately via method inspection
        }

        return new Request();
    }

    #[Test]
    public function parses_uri_from_request_uri(): void
    {
        $req = $this->createRequest(['REQUEST_URI' => '/api/v1/users?page=1']);
        $this->assertSame('/api/v1/users', $req->getPath());
    }

    #[Test]
    public function parses_method_from_server(): void
    {
        $req = $this->createRequest(['REQUEST_METHOD' => 'POST']);
        $this->assertSame('POST', $req->getMethod());
    }

    #[Test]
    public function defaults_method_to_get(): void
    {
        unset($_SERVER['REQUEST_METHOD']);
        $req = $this->createRequest([]);
        $this->assertSame('GET', $req->getMethod());
    }

    #[Test]
    public function get_input_returns_from_query_string(): void
    {
        $_GET = ['search' => 'test'];
        $req = $this->createRequest();
        $this->assertSame('test', $req->input('search'));
    }

    #[Test]
    public function input_returns_default_for_missing_key(): void
    {
        $_GET = [];
        $_POST = [];
        $req = $this->createRequest();
        $this->assertNull($req->input('nonexistent'));
        $this->assertSame('fallback', $req->input('nonexistent', 'fallback'));
    }

    #[Test]
    public function get_header_returns_value_case_insensitively(): void
    {
        $req = $this->createRequest([
            'HTTP_AUTHORIZATION' => 'Bearer test-token',
            'HTTP_X_API_KEY' => 'devKey123',
        ]);

        // The Request constructor normalizes headers via getAllHeaders()
        // In CLI mode, getallheaders() is missing so it falls back to $_SERVER HTTP_* prefixed keys
        $this->assertSame('Bearer test-token', $req->getHeaderLine('Authorization'));
        $this->assertSame('Bearer test-token', $req->getHeaderLine('authorization'));
        $this->assertSame('Bearer test-token', $req->getHeaderLine('AUTHORIZATION'));
        $this->assertSame('devKey123', $req->getHeaderLine('X-API-Key'));
        $this->assertSame('devKey123', $req->getHeaderLine('x-api-key'));
    }

    #[Test]
    public function get_header_returns_default_for_missing(): void
    {
        $req = $this->createRequest();
        $this->assertSame('', $req->getHeaderLine('Nonexistent'));
    }

    #[Test]
    public function set_and_get_attribute(): void
    {
        $req = $this->createRequest();
        $req->setAttribute('user_id', '123');
        $this->assertSame('123', $req->getAttribute('user_id'));
    }

    #[Test]
    public function get_attribute_returns_default_for_missing(): void
    {
        $req = $this->createRequest();
        $this->assertNull($req->getAttribute('nothing'));
    }

    #[Test]
    public function get_body_params_returns_empty_for_get(): void
    {
        $req = $this->createRequest(['REQUEST_METHOD' => 'GET']);
        $this->assertSame([], $req->getBodyParams());
    }

    #[Test]
    public function query_string_returns_parsed_query(): void
    {
        $req = $this->createRequest(['REQUEST_URI' => '/test?foo=bar&baz=qux']);
        $this->assertSame('bar', $req->input('foo'));
        $this->assertSame('qux', $req->input('baz'));
    }

    #[Test]
    public function get_method_returns_uppercased(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'post';
        $req = $this->createRequest(['REQUEST_METHOD' => 'post']);
        $this->assertSame('POST', $req->getMethod());
    }

    #[Test]
    public function multiple_headers_same_prefix_are_accessible(): void
    {
        $req = $this->createRequest([
            'HTTP_AUTHORIZATION' => 'Bearer token1',
            'HTTP_X_API_KEY' => 'key123',
            'HTTP_X_CSRF_TOKEN' => 'csrf-token',
        ]);

        $this->assertSame('Bearer token1', $req->getHeaderLine('Authorization'));
        $this->assertSame('key123', $req->getHeaderLine('x-api-key'));
        $this->assertSame('csrf-token', $req->getHeaderLine('X-CSRF-TOKEN'));
    }
}
