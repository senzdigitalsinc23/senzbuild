<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Logger;
use PHPUnit\Framework\TestCase;

class LoggingTest extends TestCase
{
    private string $logPath;

    protected function setUp(): void
    {
        $this->logPath = dirname(__DIR__, 2) . '/storage/logs/test.log';
        if (file_exists($this->logPath)) {
            unlink($this->logPath);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->logPath)) {
            unlink($this->logPath);
        }
    }

    public function testStructuredJsonLogging(): void
    {
        $logger = new Logger($this->logPath, 'DEBUG', 10485760, 5, true);
        $message = 'Test structured log message';
        $context = ['user_id' => 123, 'action' => 'test_log'];

        $logger->info($message, $context);

        $this->assertFileExists($this->logPath);
        $content = file_get_contents($this->logPath);
        $lines = explode("\n", trim($content));

        $this->assertCount(1, $lines);
        $data = json_decode($lines[0], true);

        $this->assertNotNull($data, 'Log entry should be valid JSON');
        $this->assertEquals('INFO', $data['level']);
        $this->assertEquals($message, $data['message']);
        $this->assertEquals(123, $data['context']['user_id']);
        $this->assertEquals('test_log', $data['context']['action']);
        $this->assertArrayHasKey('timestamp', $data);
    }

    public function testPlainTextLogging(): void
    {
        $logger = new Logger($this->logPath, 'DEBUG', 10485760, 5, false);
        $message = 'Test plain log message';

        $logger->info($message);

        $this->assertFileExists($this->logPath);
        $content = file_get_contents($this->logPath);

        $this->assertStringContainsString('INFO: Test plain log message', $content);
        $this->assertStringNotContainsString('{"timestamp":', $content);
    }
}
