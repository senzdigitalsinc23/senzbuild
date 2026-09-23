<?php
declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Controllers\Api\HealthController;
use App\Core\Health\HealthService;
use App\Core\Response;
use App\Core\Interfaces\RequestInterface;
use PHPUnit\Framework\TestCase;

class HealthTest extends TestCase
{
    private HealthService $healthService;
    private HealthController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->healthService = $this->createMock(HealthService::class);
        $this->controller = new HealthController($this->healthService);
    }

    public function testCheckReturnsHealthyWhenAllSystemsUp(): void
    {
        $this->healthService->method('runAll')->willReturn([
            'status' => 'healthy',
            'checks' => [
                'database' => ['status' => 'healthy'],
                'cache' => ['status' => 'healthy'],
            ],
        ]);

        $request = $this->createMock(RequestInterface::class);
        $response = new Response();

        $result = $this->controller->check($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode($result->getContent(), true);
        $this->assertIsArray($body);
        $this->assertEquals('healthy', $body['data']['status'] ?? $body['status'] ?? null);
    }

    public function testCheckReturnsUnhealthyWhenDatabaseIsDown(): void
    {
        $this->healthService->method('runAll')->willReturn([
            'status' => 'unhealthy',
            'checks' => [
                'database' => ['status' => 'unhealthy'],
                'cache' => ['status' => 'healthy'],
            ],
        ]);

        $request = $this->createMock(RequestInterface::class);
        $response = new Response();

        $result = $this->controller->check($request, $response);

        $this->assertEquals(503, $result->getStatusCode());
        $body = json_decode($result->getContent(), true);
        $this->assertIsArray($body);
    }

    public function testPingReturnsOk(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = new Response();

        $result = $this->controller->ping($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode($result->getContent(), true);
        $this->assertIsArray($body);
        $this->assertEquals('ok', $body['data']['status'] ?? $body['status'] ?? null);
    }
}
