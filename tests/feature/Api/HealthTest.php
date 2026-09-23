<?php
declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Controllers\Api\HealthController;
use App\Core\Cache;
use App\Core\Response;
use App\Core\Interfaces\RequestInterface;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

class HealthTest extends TestCase
{
    private $dbMock;
    private $cacheMock;
    private HealthController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dbMock = $this->createMock(PDO::class);
        $this->cacheMock = $this->createMock(Cache::class);
        $this->controller = new HealthController($this->dbMock, $this->cacheMock);
    }

    public function testCheckReturnsHealthyWhenAllSystemsUp(): void
    {
        // Mock DB query
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('fetch')->willReturn(['health_check' => 1]);
        $this->dbMock->method('query')->willReturn($stmtMock);

        // Mock Cache
        $this->cacheMock->method('get')->willReturn('ok');

        $request = $this->createMock(RequestInterface::class);
        $response = new Response();

        $result = $this->controller->check($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode($result->getContent(), true);
        $this->assertEquals('healthy', $body['status']);
        $this->assertEquals('healthy', $body['checks']['database']['status']);
        $this->assertEquals('healthy', $body['checks']['cache']['status']);
    }

    public function testCheckReturnsUnhealthyWhenDatabaseIsDown(): void
    {
        // Mock DB to throw exception
        $this->dbMock->method('query')->willThrowException(new \Exception('Connection timed out'));

        $request = $this->createMock(RequestInterface::class);
        $response = new Response();

        $result = $this->controller->check($request, $response);

        $this->assertEquals(503, $result->getStatusCode());
        $body = json_decode($result->getContent(), true);
        $this->assertEquals('unhealthy', $body['status']);
        $this->assertEquals('unhealthy', $body['checks']['database']['status']);
    }

    public function testCheckReturnsWarningWhenCacheIsDown(): void
    {
        // Mock DB to be healthy
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('fetch')->willReturn(['health_check' => 1]);
        $this->dbMock->method('query')->willReturn($stmtMock);

        // Mock Cache to throw exception
        $this->cacheMock->method('set')->willThrowException(new \Exception('Cache unavailable'));

        $request = $this->createMock(RequestInterface::class);
        $response = new Response();

        $result = $this->controller->check($request, $response);

        // Cache failure is a warning, not an unhealthy state
        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode($result->getContent(), true);
        $this->assertEquals('warning', $body['status']);
        $this->assertEquals('warning', $body['checks']['cache']['status']);
    }

    public function testPingReturnsOk(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = new Response();

        $result = $this->controller->ping($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode($result->getContent(), true);
        $this->assertEquals('ok', $body['status']);
    }
}
