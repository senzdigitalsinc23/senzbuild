<?php
require __DIR__ . '/../../vendor/autoload.php';

use App\Core\HttpTestCase;

class AuthControllerTest extends HttpTestCase
{
    public function testLoginApi()
    {
        // Simulate POST /api/login with JSON
        $response = $this->post('/api/login', [
            'email' => 'test@example.com',
            'password' => 'secret'
        ], [], ['Content-Type' => 'application/json']);

        $this->assertEquals(200, $response()->getStatusCode(), 'Login should return 200');

        $data = json_decode($response()->getContent(), true);
        $this->assertJsonContains(['status' => 'success'], $data, 'Response JSON should contain status=success');
    }

    public function testProtectedRouteRequiresAuth()
    {
        // Simulate GET /api/user without session
        $response = $this->get('/api/user');

        $this->assertEquals(401, $response()->getStatusCode(), 'Protected route should return 401 if not logged in');

        // Simulate GET /api/user with session
        $response = $this->get('/api/user', [], ['user_id' => 1]);
        $this->assertEquals(200, $response()->getStatusCode(), 'Protected route should return 200 if logged in');
    }
}

$test = new AuthControllerTest();
$test->run();
