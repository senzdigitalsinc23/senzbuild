<?php
declare(strict_types=1);

namespace Tests\Feature;

use Tests\FeatureTestCase;

class HealthTest extends FeatureTestCase
{
    public function test_health_check_returns_success(): void
    {
        $this->markTestSkipped('Feature tests require application route registration (routes/web.php)');
    }

    public function test_ping_returns_success(): void
    {
        $this->markTestSkipped('Feature tests require application route registration (routes/web.php)');
    }
}
