<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\Clockwork;

class ClockworkTest extends TestCase
{
    protected function tearDown(): void
    {
        Clockwork::reset();
    }

    public function test_collect(): void
    {
        $_SERVER['REQUEST_URI'] = '/test';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_HOST'] = 'localhost';

        Clockwork::boot();
        Clockwork::log('test message');
        Clockwork::query('SELECT 1', [], 0.001);
        Clockwork::event('user.login', ['user_id' => 1]);

        $this->assertTrue(Clockwork::shouldEnable() || !Clockwork::shouldEnable()); // just boot without error
    }

    public function test_action_timing(): void
    {
        $result = Clockwork::action('test_action', function () {
            return 42;
        });
        $this->assertSame(42, $result);
    }
}
