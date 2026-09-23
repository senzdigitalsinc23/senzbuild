<?php
declare(strict_types=1);

namespace Tests\Feature;

use Tests\FeatureTestCase;

class HealthTest extends FeatureTestCase
{
    /**
     * Test that the health check endpoint returns a successful response.
     * Verifies the structural integrity and status code of the health check.
     */
    public function test_health_check_returns_success(): void
    {
        $response = $this->get('/api/v1/health');

        $this->assertStatus(200);
        $this->assertJsonValue('success', true);
        $this->assertJsonValue('status', 'healthy');
    }

    /**
     * Test that the ping endpoint returns a successful response.
     * Verifies the fastest possible health check endpoint.
     */
    public function test_ping_returns_success(): void
    {
        $response = $this->get('/api/v1/ping');

        $this->assertStatus(200);
        $this->assertJsonValue('success', true);
        $this->assertJsonValue('status', 'ok');
    }
}
