<?php
require __DIR__ . '/../../vendor/autoload.php';

use App\Core\HttpTestCase;
use PHPUnit\Framework\TestCase;

class AuthControllerTest extends TestCase
{
    public function testLoginApi(): void
    {
        $this->markTestSkipped('Feature tests require application route registration');
    }

    public function testProtectedRouteRequiresAuth(): void
    {
        $this->markTestSkipped('Feature tests require application route registration');
    }
}
