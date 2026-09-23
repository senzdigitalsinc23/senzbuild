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
        $this->markTestSkipped('Integration tests require a live MySQL database connection');
    }
}
