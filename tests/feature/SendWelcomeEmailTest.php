<?php
declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Placeholder for email sending feature tests.
 * Requires application service configuration to run.
 */
abstract class SendWelcomeEmailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->markTestSkipped('Email feature tests require mail configuration');
    }
}
