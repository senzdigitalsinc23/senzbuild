<?php
require __DIR__ . '/../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

class UserControllerTest extends TestCase
{
    public function testRegisterUserRoute(): void
    {
        $this->markTestSkipped('Feature tests require application route registration');
    }
}

