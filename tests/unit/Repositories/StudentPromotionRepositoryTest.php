<?php

namespace Tests\Unit\Repositories;

use Tests\TestCase;
use App\Repositories\StudentPromotionRepository;
use PDO;

/**
 * Test student promotion repository batch methods
 */
class StudentPromotionRepositoryTest extends TestCase
{
    private StudentPromotionRepository $repository;
    private $mockDb;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockDb = $this->createMock(PDO::class);
        $this->repository = new StudentPromotionRepository($this->mockDb);
    }

    /**
     * Test resolveStudentNosBatch with empty array
     */
    public function testResolveStudentNosBatchWithEmptyArray(): void
    {
        $result = $this->repository->resolveStudentNosBatch([]);
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test resolveStudentNosBatch returns correct mapping
     */
    public function testResolveStudentNosBatchReturnsCorrectMapping(): void
    {
        $mockStmt = $this->createMock(\PDOStatement::class);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('fetchAll')->willReturn([
            ['student_no' => 'STU001', 'id' => 1],
            ['student_no' => 'STU002', 'id' => 2],
            ['student_no' => 'STU003', 'id' => 3]
        ]);

        $this->mockDb->method('prepare')->willReturn($mockStmt);

        $result = $this->repository->resolveStudentNosBatch(['STU001', 'STU002', 'STU003']);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertEquals(1, $result['STU001']);
        $this->assertEquals(2, $result['STU002']);
        $this->assertEquals(3, $result['STU003']);
    }

    /**
     * Test hasBeenPromotedBatch with empty array
     */
    public function testHasBeenPromotedBatchWithEmptyArray(): void
    {
        $result = $this->repository->hasBeenPromotedBatch([], 1);
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test hasBeenPromotedBatch returns correct format
     */
    public function testHasBeenPromotedBatchReturnsCorrectFormat(): void
    {
        $mockStmt = $this->createMock(\PDOStatement::class);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('fetchAll')->willReturn([
            ['student_id' => 1],
            ['student_id' => 3]
        ]);

        $this->mockDb->method('prepare')->willReturn($mockStmt);

        $result = $this->repository->hasBeenPromotedBatch([1, 2, 3, 4], 1);

        $this->assertIsArray($result);
        $this->assertTrue($result[1]);
        $this->assertFalse($result[2]);
        $this->assertTrue($result[3]);
        $this->assertFalse($result[4]);
    }

    /**
     * Test getStudentCurrentClassesBatch with empty array
     */
    public function testGetStudentCurrentClassesBatchWithEmptyArray(): void
    {
        $result = $this->repository->getStudentCurrentClassesBatch([], 1);
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test getStudentCurrentClassesBatch returns correct mapping
     */
    public function testGetStudentCurrentClassesBatchReturnsCorrectMapping(): void
    {
        $mockStmt = $this->createMock(\PDOStatement::class);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('fetchAll')->willReturn([
            ['student_id' => 1, 'class_id' => 10],
            ['student_id' => 2, 'class_id' => 11],
            ['student_id' => 3, 'class_id' => 12]
        ]);

        $this->mockDb->method('prepare')->willReturn($mockStmt);

        $result = $this->repository->getStudentCurrentClassesBatch([1, 2, 3], 1);

        $this->assertIsArray($result);
        $this->assertEquals(10, $result[1]);
        $this->assertEquals(11, $result[2]);
        $this->assertEquals(12, $result[3]);
    }

    /**
     * Test getNextClassesBatch with empty array
     */
    public function testGetNextClassesBatchWithEmptyArray(): void
    {
        $result = $this->repository->getNextClassesBatch([]);
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test getNextClassesBatch returns correct mapping
     */
    public function testGetNextClassesBatchReturnsCorrectMapping(): void
    {
        $mockStmt = $this->createMock(\PDOStatement::class);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('fetchAll')->willReturn([
            ['id' => 1, 'next_class_id' => 2],
            ['id' => 2, 'next_class_id' => 3],
            ['id' => 3, 'next_class_id' => null]
        ]);

        $this->mockDb->method('prepare')->willReturn($mockStmt);

        $result = $this->repository->getNextClassesBatch([1, 2, 3]);

        $this->assertIsArray($result);
        $this->assertEquals(2, $result[1]);
        $this->assertEquals(3, $result[2]);
        $this->assertNull($result[3]);
    }

    /**
     * Test batch methods use IN clause for efficiency
     */
    public function testBatchMethodsUseInClause(): void
    {
        $mockStmt = $this->createMock(\PDOStatement::class);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('fetchAll')->willReturn([]);

        // Verify SQL contains IN clause
        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('IN'))
            ->willReturn($mockStmt);

        $this->repository->resolveStudentNosBatch(['STU001', 'STU002']);
    }

    /**
     * Test batch methods handle large arrays
     */
    public function testBatchMethodsHandleLargeArrays(): void
    {
        $mockStmt = $this->createMock(\PDOStatement::class);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('fetchAll')->willReturn([]);

        $this->mockDb->method('prepare')->willReturn($mockStmt);

        // Test with 100 items
        $largeArray = range(1, 100);
        $result = $this->repository->hasBeenPromotedBatch($largeArray, 1);

        $this->assertIsArray($result);
        $this->assertCount(100, $result);
    }
}
