<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\Database;
use App\Core\DbTransaction;

/**
 * Integration test base class — wraps each test in a database transaction
 * and rolls back automatically, preventing test pollution.
 *
 * Usage:
 *   class UserIntegrationTest extends IntegrationTestCase
 *   {
 *       public function test_create_user()
 *       {
 *           $user = User::create(['name' => 'Test User', 'email' => 'test@example.com']);
 *           $this->assertNotNull($user->id);
 *           $this->assertSame('Test User', $user->name);
 *       }
 *
 *       public function test_user_not_persisted_after_rollback()
 *       {
 *           // After this test, the transaction is rolled back
 *           $this->assertSame(0, User::count());
 *       }
 *   }
 */
abstract class IntegrationTestCase extends TestCase
{
    /**
     * Begin database transaction before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        DbTransaction::begin();
    }

    /**
     * Roll back database transaction after each test.
     */
    protected function tearDown(): void
    {
        DbTransaction::rollback();
        parent::tearDown();
    }

    /**
     * Get the database connection.
     */
    protected function db(): Database
    {
        return Database::getInstance()->getConnection();
    }

    /**
     * Assert a table has a specific row count.
     */
    protected function assertTableCount(string $table, int $expected, string $message = ''): void
    {
        $db = $this->db();
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM `{$table}`");
        $stmt->execute();
        $actual = (int)$stmt->fetchColumn();
        $this->assertSame($expected, $actual, $message ?: "Expected {$table} to have {$expected} rows, got {$actual}");
    }

    /**
     * Assert a record exists in a table.
     */
    protected function assertRecordExists(string $table, array $where, string $message = ''): void
    {
        $db = $this->db();
        $conditions = implode(' AND ', array_map(fn($k) => "`{$k}` = :{$k}", array_keys($where)));
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM `{$table}` WHERE {$conditions}");
        $stmt->execute($where);
        $count = (int)$stmt->fetchColumn();
        $this->assertGreaterThan(0, $count, $message ?: "Expected record in {$table} with " . json_encode($where));
    }

    /**
     * Assert a record does not exist in a table.
     */
    protected function assertRecordMissing(string $table, array $where, string $message = ''): void
    {
        $db = $this->db();
        $conditions = implode(' AND ', array_map(fn($k) => "`{$k}` = :{$k}", array_keys($where)));
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM `{$table}` WHERE {$conditions}");
        $stmt->execute($where);
        $count = (int)$stmt->fetchColumn();
        $this->assertSame(0, $count, $message ?: "Expected no record in {$table} with " . json_encode($where));
    }

    /**
     * Get all records from a table.
     */
    protected function getRecords(string $table, array $where = [], string $orderBy = '', string $order = 'ASC'): array
    {
        $db = $this->db();
        $sql = "SELECT * FROM `{$table}`";
        if (!empty($where)) {
            $conditions = implode(' AND ', array_map(fn($k) => "`{$k}` = :{$k}", array_keys($where)));
            $sql .= " WHERE {$conditions}";
        }
        if ($orderBy) {
            $sql .= " ORDER BY `{$orderBy}` {$order}";
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($where);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Seed test data into a table.
     */
    protected function seedTable(string $table, array $records): void
    {
        $db = $this->db();
        if (empty($records)) {
            return;
        }

        $columns = array_keys($records[0]);
        $placeholders = implode(',', array_map(fn($col) => ":{$col}", $columns));
        $sql = "INSERT INTO `{$table}` ({$columns}) VALUES ({$placeholders})";

        foreach ($records as $record) {
            $stmt = $db->prepare($sql);
            $stmt->execute($record);
        }
    }

    /**
     * Truncate a table (respecting transactions).
     */
    protected function truncateTable(string $table): void
    {
        $db = $this->db();
        $db->query("TRUNCATE TABLE `{$table}`");
    }

    /**
     * Get the PDO instance for raw queries.
     */
    protected function pdo(): \PDO
    {
        return $this->db()->getConnection();
    }
}
