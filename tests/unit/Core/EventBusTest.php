<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\EventBus;

class EventBusTest extends TestCase
{
    protected function tearDown(): void
    {
        EventBus::clearLog();
        EventBus::setMaxLogSize(10000);
    }

    public function test_exact_match_dispatch(): void
    {
        $received = [];
        $bus = new EventBus(null);
        $bus->subscribe(UserRegisteredEvent::class, function ($e) use (&$received) {
            $received[] = $e->userId;
        });
        $bus->publish(new UserRegisteredEvent('user-1'));
        $this->assertSame(['user-1'], $received);
    }

    public function test_wildcard_match(): void
    {
        $received = [];
        $bus = new EventBus(null);
        $bus->subscribe('OrderCreated*', function ($e) use (&$received) {
            $received[] = $e->type;
        });
        $bus->publish(new OrderCreatedEvent('created'));
        $bus->publish(new OrderCreatedShippedEvent('shipped'));
        $this->assertCount(2, $received);
    }

    public function test_replay(): void
    {
        $bus = new EventBus(null);
        $bus->publish(new UserRegisteredEvent('u1'));
        $bus->publish(new UserRegisteredEvent('u2'));
        $bus->publish(new OrderCreatedEvent('o1'));

        $events = EventBus::replay('UserRegisteredEvent');
        $this->assertCount(2, $events);
    }

    public function test_log_size_limit(): void
    {
        EventBus::setMaxLogSize(2);
        $bus = new EventBus(null);
        $bus->publish(new UserRegisteredEvent('a'));
        $bus->publish(new UserRegisteredEvent('b'));
        $bus->publish(new UserRegisteredEvent('c'));
        $this->assertLessThanOrEqual(2, EventBus::logSize());
    }
}

class UserRegisteredEvent
{
    public function __construct(public string $userId) {}
}

class OrderCreatedEvent
{
    public function __construct(public string $type) {}
}

class OrderCreatedShippedEvent
{
    public function __construct(public string $type) {}
}
