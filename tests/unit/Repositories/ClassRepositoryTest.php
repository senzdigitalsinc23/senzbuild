<?php

namespace Tests\Unit\Repositories;

use Tests\TestCase;
use App\Repositories\ClassRepository;
use App\Core\Cache;
use PDO;

/**
 * Test class repository functionality
 */
class ClassRepositoryTest extends TestCase
{
    private ClassRepository $repository;
    private $mockDb;
    private $mockCache;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks
        $this->mockDb = $this->createMock(PDO::class);
        $this->mockCache = $this->createMock(Cache::class);

        // Create repository with mocked dependencies
        $this->repository = new ClassRepository($this->mockDb, $this->mockCache);
    }

    /**
     * Test dependency injection in constructor
     */
    public function testConstructorWithDependencyInjection(): void
    {
        $repo = new ClassRepository($this->mockDb, $this->mockCache);
        $this->assertInstanceOf(ClassRepository::class, $repo);
    }

    /**
     * Test constructor with default dependencies
     */
    public function testConstructorWithDefaults(): void
    {
        // This will use real Database and Cache instances
        $repo = new ClassRepository();
        $this->assertInstanceOf(ClassRepository::class, $repo);
    }

    /**
     * Test existsBatch method with empty array
     */
    public function testExistsBatchWithEmptyArray(): void
    {
        $result = $this->repository->existsBatch([]);
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test existsBatch method returns correct format
     */
    public function testExistsBatchReturnsCorrectFormat(): void
    {
        // Mock PDOStatement
        $mockStmt = $this->createMock(\PDOStatement::class);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('fetchAll')->willReturn([
            ['id' => 1],
            ['id' => 3],
            ['id' => 5]
        ]);

        // Mock PDO to return the statement
        $this->mockDb->method('prepare')->willReturn($mockStmt);

        $result = $this->repository->existsBatch([1, 2, 3, 4, 5]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey(1, $result);
        $this->assertArrayHasKey(3, $result);
        $this->assertArrayHasKey(5, $result);
        $this->assertArrayNotHasKey(2, $result);
        $this->assertArrayNotHasKey(4, $result);
        $this->assertTrue($result[1]);
        $this->assertTrue($result[3]);
        $this->assertTrue($result[5]);
    }

    /**
     * Test existsBatch uses IN clause
     */
    public function testExistsBatchUsesInClause(): void
    {
        $mockStmt = $this->createMock(\PDOStatement::class);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('fetchAll')->willReturn([]);

        // Verify SQL contains IN clause
        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('IN'))
            ->willReturn($mockStmt);

        $this->repository->existsBatch([1, 2, 3]);
    }
}
