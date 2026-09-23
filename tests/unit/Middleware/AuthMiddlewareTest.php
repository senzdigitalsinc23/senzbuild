<?php

namespace Tests\Unit\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Middleware\AuthMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(AuthMiddleware::class)]
class AuthMiddlewareTest extends TestCase
{
    private AuthMiddleware $middleware;
    private Request $request;
    private Response $response;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new AuthMiddleware();
        $this->request = $this->createMock(Request::class);
        $this->response = new Response();

        // Clean session between tests
        Session::remove('user');
        Session::remove('user_id');
    }

    private function makeNext(): callable
    {
        return function (Request $req, Response $res): Response {
            $res->setStatusCode(200);
            $res->setContent(json_encode(['success' => true]));
            return $res;
        };
    }

    #[Test]
    public function passes_when_user_in_session(): void
    {
        Session::set('user', ['id' => '1', 'name' => 'Test']);

        $result = $this->middleware->handle($this->request, $this->response, $this->makeNext());
        $this->assertSame(200, $result->getStatusCode());
    }

    #[Test]
    public function returns_401_when_no_auth_header_and_no_session(): void
    {
        $this->request->method('getHeader')->with('Authorization')->willReturn(null);

        $result = $this->middleware->handle($this->request, $this->response, $this->makeNext());
        $this->assertSame(401, $result->getStatusCode());
        $body = json_decode($result->getContent(), true);
        $this->assertSame('Unauthorized', $body['message']);
    }

    #[Test]
    public function returns_401_when_header_is_not_bearer(): void
    {
        $this->request->method('getHeader')->with('Authorization')->willReturn('Basic dGVzdDp0ZXN0');

        $result = $this->middleware->handle($this->request, $this->response, $this->makeNext());
        $this->assertSame(401, $result->getStatusCode());
    }

    #[Test]
    public function returns_401_when_header_is_empty(): void
    {
        $this->request->method('getHeader')->with('Authorization')->willReturn('');

        $result = $this->middleware->handle($this->request, $this->response, $this->makeNext());
        $this->assertSame(401, $result->getStatusCode());
    }

    #[Test]
    public function returns_json_error_response(): void
    {
        $this->request->method('getHeader')->with('Authorization')->willReturn(null);

        $result = $this->middleware->handle($this->request, $this->response, $this->makeNext());
        $this->assertSame(401, $result->getStatusCode());
        $body = json_decode($result->getContent(), true);
        $this->assertArrayHasKey('success', $body);
        $this->assertArrayHasKey('message', $body);
        $this->assertFalse($body['success']);
    }
}
