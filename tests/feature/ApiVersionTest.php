<?php
declare(strict_types=1);

namespace Tests\Feature;

use Tests\FeatureTestCase;

class ApiVersionTest extends FeatureTestCase
{
    /**
     * Test that the API structure follows the expected versioning.
     */
    public function test_api_v1_prefix_is_reachable(): void
    {
        // We use the health check as a proxy to verify v1 routing
        $response = $this->get('/api/v1/health');

        $this->assertStatus(200);
        $this->assertJsonValue('success', true);
    }

    /**
     * Test that undefined routes return a 404 (mapped through ExceptionMapper).
     */
    public function test_undefined_route_returns_404(): void
    {
        $response = $this->get('/api/v1/this-route-does-not-exist');

        // Since the Router throws an exception for not found,
        // the global handler should map it.
        // In our simulation, the Router returns a 404 Response.
        $this->assertStatus(404);
    }
}
