<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Test Database Transaction — wraps each test in a transaction and rolls back.
 *
 * Usage:
 *   abstract class DbTestCase extends TestCase
 *   {
 *       protected function setUp(): void
 *       {
 *           parent::setUp();
 *           DbTransaction::begin();
 *       }
 *
 *       protected function tearDown(): void
 *       {
 *           DbTransaction::rollback();
 *           parent::tearDown();
 *       }
 *   }
 */
class DbTransaction
{
    protected static ?\PDO $pdo = null;
    protected static int $depth = 0;

    /**
     * Begin a database transaction for testing.
     */
    public static function begin(): void
    {
        if (self::$depth === 0) {
            $db = Database::getInstance()->getConnection();
            self::$pdo = $db;
            $db->beginTransaction();
        }
        self::$depth++;
    }

    /**
     * Roll back the test transaction.
     */
    public static function rollback(): void
    {
        self::$depth--;
        if (self::$depth <= 0 && self::$pdo !== null) {
            if (self::$pdo->inTransaction()) {
                self::$pdo->rollBack();
            }
            self::$pdo = null;
            self::$depth = 0;
        }
    }

    /**
     * Commit (for tests that need to persist).
     */
    public static function commit(): void
    {
        self::$depth--;
        if (self::$depth <= 0 && self::$pdo !== null) {
            if (self::$pdo->inTransaction()) {
                self::$pdo->commit();
            }
            self::$pdo = null;
            self::$depth = 0;
        }
    }

    /**
     * Get the PDO instance used for transactions.
     */
    public static function getPdo(): ?\PDO
    {
        return self::$pdo;
    }

    /**
     * Check if a transaction is active.
     */
    public static function isActive(): bool
    {
        return self::$pdo !== null && self::$pdo->inTransaction();
    }

    /**
     * Reset state (for CLI / non-test usage).
     */
    public static function reset(): void
    {
        self::$pdo = null;
        self::$depth = 0;
    }
}
