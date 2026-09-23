<?php
declare(strict_types=1);

namespace Tests\Feature;

use Tests\FeatureTestCase;

class ApiVersionTest extends FeatureTestCase
{
    public function test_api_v1_prefix_is_reachable(): void
    {
        $this->markTestSkipped('Feature tests require application route registration (routes/web.php)');
    }

    public function test_undefined_route_returns_404(): void
    {
        $this->markTestSkipped('Feature tests require application route registration (routes/web.php)');
    }
}
