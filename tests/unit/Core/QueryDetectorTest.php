<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\QueryDetector;

class QueryDetectorTest extends TestCase
{
    protected function tearDown(): void
    {
        QueryDetector::reset();
        QueryDetector::disable();
    }

    public function test_enabled_records_queries(): void
    {
        QueryDetector::enable();
        QueryDetector::record('SELECT * FROM users', [], 'App/Controllers/UserController.php:42');
        $this->assertSame(1, QueryDetector::getTotalQueries());
    }

    public function test_disabled_does_not_record(): void
    {
        QueryDetector::disable();
        QueryDetector::record('SELECT * FROM users');
        $this->assertSame(0, QueryDetector::getTotalQueries());
    }

    public function test_detects_n_plus_one_pattern(): void
    {
        QueryDetector::enable();
        QueryDetector::setMaxQueries(3);

        for ($i = 0; $i < 5; $i++) {
            QueryDetector::record('SELECT * FROM orders WHERE user_id = :id', ['id' => 1], 'UserController::show');
        }

        $warnings = QueryDetector::getWarnings();
        $this->assertCount(1, $warnings);
        $this->assertSame(5, $warnings[0]['count']);
    }

    public function test_no_warning_when_under_threshold(): void
    {
        QueryDetector::enable();
        QueryDetector::setMaxQueries(10);

        for ($i = 0; $i < 3; $i++) {
            QueryDetector::record('SELECT * FROM orders');
        }

        $warnings = QueryDetector::getWarnings();
        $this->assertCount(0, $warnings);
    }

    public function test_reset_clears_data(): void
    {
        QueryDetector::enable();
        QueryDetector::record('SELECT 1');
        $this->assertSame(1, QueryDetector::getTotalQueries());

        QueryDetector::reset();
        $this->assertSame(0, QueryDetector::getTotalQueries());
    }

    public function test_queries_grouped_by_hash(): void
    {
        QueryDetector::enable();
        QueryDetector::record('SELECT * FROM users');
        QueryDetector::record('SELECT * FROM users');
        QueryDetector::record('SELECT * FROM orders');

        $queries = QueryDetector::getQueries();
        $this->assertCount(2, $queries); // 2 unique queries
        $this->assertCount(2, reset($queries)); // first query called twice
    }
}
