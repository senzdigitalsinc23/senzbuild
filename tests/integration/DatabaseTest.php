<?php
declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Core\Database;
use PDOException;

class DatabaseTest extends TestCase
{
    public function testConnection(): void
    {
        try {
            $db = Database::getInstance()->getConnection();
            $this->assertInstanceOf(\PDO::class, $db);

            $stmt = $db->query('SELECT 1');
            $this->assertEquals(1, $stmt->fetchColumn());
        } catch (PDOException $e) {
            $this->markTestSkipped('Database connection not available for integration tests: ' . $e->getMessage());
        }
    }
}
