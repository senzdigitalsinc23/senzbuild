<?php

namespace Tests\Unit\Middleware;

use Tests\TestCase;
use App\Middleware\JWTMiddleware;
use App\Core\Request;
use App\Core\Response;
use Firebase\JWT\JWT;

/**
 * Test JWT middleware functionality
 */
class JWTMiddlewareTest extends TestCase
{
    private string $testSecret = 'test_jwt_secret_key_for_testing';

    /**
     * Test middleware requires JWT_SECRET
     */
    public function testMiddlewareRequiresJwtSecret(): void
    {
        unset($_ENV['JWT_SECRET']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JWT_SECRET environment variable is not configured');

        new JWTMiddleware();
    }

    /**
     * Test middleware accepts custom secret
     */
    public function testMiddlewareAcceptsCustomSecret(): void
    {
        $middleware = new JWTMiddleware($this->testSecret);
        $this->assertInstanceOf(JWTMiddleware::class, $middleware);
    }

    /**
     * Test middleware rejects request without authorization header
     */
    public function testRejectsRequestWithoutAuthHeader(): void
    {
        $middleware = new JWTMiddleware($this->testSecret);
        
        $request = $this->createMock(Request::class);
        $request->method('header')->with('Authorization')->willReturn(null);
        
        $response = new Response();
        
        $next = function() {
            $this->fail('Next middleware should not be called');
        };

        $result = $middleware->handle($request, $response, $next);

        $this->assertEquals(401, $result->getStatusCode());
        $content = json_decode($result->getContent(), true);
        $this->assertEquals('Unauthorized', $content['error']);
    }

    /**
     * Test middleware rejects malformed authorization header
     */
    public function testRejectsMalformedAuthHeader(): void
    {
        $middleware = new JWTMiddleware($this->testSecret);
        
        $request = $this->createMock(Request::class);
        $request->method('header')->with('Authorization')->willReturn('InvalidFormat token123');
        
        $response = new Response();
        
        $next = function() {
            $this->fail('Next middleware should not be called');
        };

        $result = $middleware->handle($request, $response, $next);

        $this->assertEquals(401, $result->getStatusCode());
    }

    /**
     * Test middleware rejects invalid token
     */
    public function testRejectsInvalidToken(): void
    {
        $middleware = new JWTMiddleware($this->testSecret);
        
        $request = $this->createMock(Request::class);
        $request->method('header')->with('Authorization')->willReturn('Bearer invalid.token.here');
        
        $response = new Response();
        
        $next = function() {
            $this->fail('Next middleware should not be called');
        };

        $result = $middleware->handle($request, $response, $next);

        $this->assertEquals(401, $result->getStatusCode());
        $content = json_decode($result->getContent(), true);
        $this->assertEquals('Invalid or expired token', $content['error']);
    }

    /**
     * Test middleware accepts valid token
     */
    public function testAcceptsValidToken(): void
    {
        $middleware = new JWTMiddleware($this->testSecret);
        
        // Generate valid token
        $payload = [
            'sub' => 1,
            'email' => 'test@example.com',
            'iat' => time(),
            'exp' => time() + 3600
        ];
        $token = JWT::encode($payload, $this->testSecret, 'HS256');
        
        $request = $this->createMock(Request::class);
        $request->method('header')->with('Authorization')->willReturn("Bearer $token");
        
        $response = new Response();
        
        $nextCalled = false;
        $next = function($req, $res) use (&$nextCalled) {
            $nextCalled = true;
            return $res;
        };

        $result = $middleware->handle($request, $response, $next);

        $this->assertTrue($nextCalled, 'Next middleware should be called with valid token');
    }

    /**
     * Test middleware rejects expired token
     */
    public function testRejectsExpiredToken(): void
    {
        $middleware = new JWTMiddleware($this->testSecret);
        
        // Generate expired token
        $payload = [
            'sub' => 1,
            'email' => 'test@example.com',
            'iat' => time() - 7200,
            'exp' => time() - 3600 // Expired 1 hour ago
        ];
        $token = JWT::encode($payload, $this->testSecret, 'HS256');
        
        $request = $this->createMock(Request::class);
        $request->method('header')->with('Authorization')->willReturn("Bearer $token");
        
        $response = new Response();
        
        $next = function() {
            $this->fail('Next middleware should not be called for expired token');
        };

        $result = $middleware->handle($request, $response, $next);

        $this->assertEquals(401, $result->getStatusCode());
    }

    /**
     * Test middleware handles case-insensitive Bearer prefix
     */
    public function testHandlesCaseInsensitiveBearerPrefix(): void
    {
        $middleware = new JWTMiddleware($this->testSecret);
        
        $payload = [
            'sub' => 1,
            'iat' => time(),
            'exp' => time() + 3600
        ];
        $token = JWT::encode($payload, $this->testSecret, 'HS256');
        
        $request = $this->createMock(Request::class);
        $request->method('header')->with('Authorization')->willReturn("bearer $token");
        
        $response = new Response();
        
        $nextCalled = false;
        $next = function($req, $res) use (&$nextCalled) {
            $nextCalled = true;
            return $res;
        };

        $middleware->handle($request, $response, $next);

        $this->assertTrue($nextCalled);
    }
}
