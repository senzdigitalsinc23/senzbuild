<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Core\HttpTestCase;
use App\Core\Response;

class UserControllerTest extends HttpTestCase
{
    public function testRegisterUserRoute()
    {
        $response = $this->post('/register', [
            'email' => 'test@example.com',
            'password' => 'secret'
        ]);

        $this->assertEquals(200, $response()->getStatusCode(), 'Register route should return 200');
        $this->assertTrue(str_contains($response()->getContent(), 'User registered'), 'Response should contain success message');
    }
}

$test = new UserControllerTest();
$test->run();

