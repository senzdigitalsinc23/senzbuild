<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\SyslogLogger;
use App\Core\StackdriverLogger;

class LogChannelsTest extends TestCase
{
    public function test_syslog_logger_implements_interface(): void
    {
        $logger = new SyslogLogger();
        $this->assertInstanceOf(\Psr\Log\LoggerInterface::class, $logger);
    }

    public function test_syslog_logger_does_not_throw(): void
    {
        $logger = new SyslogLogger();
        // Should not throw even if syslog is unavailable
        $logger->info('test message');
        $logger->error('test error');
        $logger->debug('test debug');
        $this->assertTrue(true);
    }

    public function test_stackdriver_logger_implements_interface(): void
    {
        $logger = new StackdriverLogger('test-project');
        $this->assertInstanceOf(\Psr\Log\LoggerInterface::class, $logger);
    }

    public function test_stackdriver_logger_does_not_throw(): void
    {
        $logger = new StackdriverLogger('test-project');
        // Should not throw even without valid credentials
        $logger->info('test message');
        $logger->error('test error');
        $this->assertTrue(true);
    }

    public function test_stackdriver_buffer_flush(): void
    {
        $logger = new StackdriverLogger('test-project');
        $logger->flush(); // Should not throw
        $this->assertTrue(true);
    }
}
