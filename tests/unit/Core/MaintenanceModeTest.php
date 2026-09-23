<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\MaintenanceMode;
use App\Core\DrainMode;

class MaintenanceModeTest extends TestCase
{
    protected function setUp(): void { MaintenanceMode::disable(); DrainMode::cancel(); } protected function tearDown(): void
    {
        MaintenanceMode::disable();
        DrainMode::cancel();
    }

    public function test_default_disabled(): void
    {
        $this->assertFalse(MaintenanceMode::enabled());
    }

    public function test_enable_and_disable(): void
    {
        MaintenanceMode::enable();
        $this->assertTrue(MaintenanceMode::enabled());
        MaintenanceMode::disable();
        $this->assertFalse(MaintenanceMode::enabled());
    }

    public function test_get_message(): void
    {
        MaintenanceMode::enable('Being upgraded');
        $this->assertSame('Being upgraded', MaintenanceMode::getMessage());
    }

    public function test_bypass_uris(): void
    {
        MaintenanceMode::enable();
        $this->assertTrue(MaintenanceMode::shouldBypass('/health'));
        $this->assertFalse(MaintenanceMode::shouldBypass('/api/v1/users'));
    }

    public function test_drain_mode(): void
    {
        $this->assertFalse(DrainMode::isDraining());
        DrainMode::signal();
        $this->assertTrue(DrainMode::isDraining());
        DrainMode::cancel();
        $this->assertFalse(DrainMode::isDraining());
    }

    public function test_drain_timeout(): void
    {
        DrainMode::setTimeout(1);
        DrainMode::signal();
        // Manually set startedAt far in the past
        $ref = new \ReflectionClass(DrainMode::class);
        $prop = $ref->getProperty('startedAt');
        $prop->setAccessible(true);
        $prop->setValue(null, time() - 10);
        $this->assertTrue(DrainMode::isTimedOut());
        DrainMode::cancel();
    }
}

