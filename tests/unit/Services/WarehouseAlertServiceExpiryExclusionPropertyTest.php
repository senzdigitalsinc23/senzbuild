<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\WarehouseAlertService;
use PDO;

/**
 * Property test for expiry alert exclusion rules (Property 5).
 *
 * Property 5: Expiry alert excludes zero-quantity and null-expiry rows
 *   For any stock row where quantity = 0 or expiry_date IS NULL,
 *   the row SHALL NOT appear in expiring-soon results.
 *
 * Validates: Requirements 3.3, 3.4
 *
 * Uses MySQL with temporary tables so no persistent data is written.
 * Temporary tables are session-scoped and dropped automatically on disconnect.
 */
class WarehouseAlertServiceExpiryExclusionPropertyTest extends TestCase
{
    private WarehouseAlertService $service;

    // -----------------------------------------------------------------------
    // Setup / teardown
    // -----------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = $this->getMysqlConnection();
        $this->createTempSchema();
        $this->service = new WarehouseAlertService($this->db);
    }

    protected function tearDown(): void
    {
        if ($this->db !== null) {
            $this->db->exec("DROP TEMPORARY TABLE IF EXISTS stock");
            $this->db->exec("DROP TEMPORARY TABLE IF EXISTS products");
            $this->db->exec("DROP TEMPORARY TABLE IF EXISTS warehouses");
        }
        parent::tearDown();
    }

    private function getMysqlConnection(): PDO
    {
        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $user = $_ENV['DB_USER'] ?? 'root';
        $pass = $_ENV['DB_PASS'] ?? '';
        $name = $_ENV['DB_NAME'] ?? 'multishop_manager';

        return new PDO(
            "mysql:host=$host;dbname=$name;charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    private function createTempSchema(): void
    {
        $this->db->exec("
            CREATE TEMPORARY TABLE warehouses (
                id        VARCHAR(36) NOT NULL PRIMARY KEY,
                name      VARCHAR(255) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1
            ) ENGINE=MEMORY
        ");

        $this->db->exec("
            CREATE TEMPORARY TABLE products (
                id         VARCHAR(36) NOT NULL PRIMARY KEY,
                name       VARCHAR(255) NOT NULL,
                sku        VARCHAR(100) NOT NULL,
                min_stock  INT NOT NULL DEFAULT 0,
                unit       VARCHAR(50) NOT NULL DEFAULT 'pcs',
                store_type VARCHAR(50) NOT NULL DEFAULT 'supermarket',
                is_active  TINYINT(1) NOT NULL DEFAULT 1
            ) ENGINE=MEMORY
        ");

        $this->db->exec("
            CREATE TEMPORARY TABLE stock (
                id           VARCHAR(36) NOT NULL PRIMARY KEY,
                product_id   VARCHAR(36) NOT NULL,
                warehouse_id VARCHAR(36) NOT NULL,
                batch_number VARCHAR(100) DEFAULT NULL,
                expiry_date  DATE DEFAULT NULL,
                quantity     INT NOT NULL DEFAULT 0
            ) ENGINE=MEMORY
        ");
    }

    // -----------------------------------------------------------------------
    // Seed helpers
    // -----------------------------------------------------------------------

    private function seedWarehouse(string $id = 'wh-1', string $name = 'Main Warehouse'): void
    {
        $this->db->prepare(
            "INSERT INTO warehouses (id, name, is_active) VALUES (:id, :name, 1)"
        )->execute(['id' => $id, 'name' => $name]);
    }

    private function seedProduct(string $id, string $storeType = 'supermarket'): void
    {
        $this->db->prepare("
            INSERT INTO products (id, name, sku, min_stock, unit, store_type, is_active)
            VALUES (:id, :name, :sku, 10, 'pcs', :store_type, 1)
        ")->execute([
            'id'         => $id,
            'name'       => "Product $id",
            'sku'        => "SKU-$id",
            'store_type' => $storeType,
        ]);
    }

    private function seedStock(
        string $stockId,
        string $productId,
        int $quantity,
        ?string $expiryDate,
        string $warehouseId = 'wh-1'
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO stock (id, product_id, warehouse_id, batch_number, expiry_date, quantity)
            VALUES (:id, :product_id, :warehouse_id, NULL, :expiry_date, :quantity)
        ");
        $stmt->execute([
            'id'           => $stockId,
            'product_id'   => $productId,
            'warehouse_id' => $warehouseId,
            'expiry_date'  => $expiryDate,
            'quantity'     => $quantity,
        ]);
    }

    /** Collect stock_ids returned by getExpiringSoon(). */
    private function getExpiringSoonIds(int $days = 60): array
    {
        $rows = $this->service->getExpiringSoon($days);
        return array_column($rows, 'stock_id');
    }

    // -----------------------------------------------------------------------
    // Property 5a — exclusion: quantity = 0 (even if expiry_date is within window)
    // -----------------------------------------------------------------------

    /**
     * @dataProvider zeroQuantityExpiryDatesProvider
     *
     * For any stock row with quantity = 0, it SHALL NOT appear in expiring-soon
     * results even when expiry_date is within the look-ahead window.
     *
     * Validates: Requirement 3.3
     */
    public function testZeroQuantityRowExcludedEvenWithExpiryWithinWindow(string $expiryDate): void
    {
        $this->seedWarehouse();
        $this->seedProduct('p-1');
        $this->seedStock('s-1', 'p-1', 0, $expiryDate);

        $ids = $this->getExpiringSoonIds(60);

        $this->assertNotContains(
            's-1',
            $ids,
            "Expected NO expiry alert for quantity=0 row with expiry_date=$expiryDate"
        );
    }

    /** @return array<string, array{string}> */
    public static function zeroQuantityExpiryDatesProvider(): array
    {
        $today = new \DateTimeImmutable('today');

        return [
            'expiry_today'          => [$today->format('Y-m-d')],
            'expiry_in_1_day'       => [$today->modify('+1 day')->format('Y-m-d')],
            'expiry_in_30_days'     => [$today->modify('+30 days')->format('Y-m-d')],
            'expiry_in_59_days'     => [$today->modify('+59 days')->format('Y-m-d')],
            'expiry_in_60_days'     => [$today->modify('+60 days')->format('Y-m-d')],
            'already_expired_1_day' => [$today->modify('-1 day')->format('Y-m-d')],
            'already_expired_30d'   => [$today->modify('-30 days')->format('Y-m-d')],
        ];
    }

    // -----------------------------------------------------------------------
    // Property 5b — exclusion: expiry_date IS NULL (even if quantity > 0)
    // -----------------------------------------------------------------------

    /**
     * @dataProvider positiveQuantityProvider
     *
     * For any stock row with expiry_date = NULL, it SHALL NOT appear in
     * expiring-soon results even when quantity > 0.
     *
     * Validates: Requirement 3.4
     */
    public function testNullExpiryRowExcludedEvenWithPositiveQuantity(int $quantity): void
    {
        $this->seedWarehouse();
        $this->seedProduct('p-1');
        $this->seedStock('s-1', 'p-1', $quantity, null);

        $ids = $this->getExpiringSoonIds(60);

        $this->assertNotContains(
            's-1',
            $ids,
            "Expected NO expiry alert for null expiry_date row with quantity=$quantity"
        );
    }

    /** @return array<string, array{int}> */
    public static function positiveQuantityProvider(): array
    {
        $cases = [];

        mt_srand(42);

        foreach ([1, 5, 10, 50, 100, 500, 1000] as $q) {
            $cases["quantity=$q"] = [$q];
        }

        for ($i = 0; $i < 10; $i++) {
            $q = mt_rand(1, 1000);
            $cases["random_qty_$i (qty=$q)"] = [$q];
        }

        return $cases;
    }

    // -----------------------------------------------------------------------
    // Property 5c — exclusion: quantity = 0 AND expiry_date IS NULL
    // -----------------------------------------------------------------------

    /**
     * A row with both quantity = 0 AND expiry_date = NULL must not appear.
     *
     * Validates: Requirements 3.3, 3.4
     */
    public function testRowWithZeroQuantityAndNullExpiryIsExcluded(): void
    {
        $this->seedWarehouse();
        $this->seedProduct('p-1');
        $this->seedStock('s-1', 'p-1', 0, null);

        $ids = $this->getExpiringSoonIds(60);

        $this->assertNotContains(
            's-1',
            $ids,
            "Expected NO expiry alert for row with quantity=0 AND expiry_date=NULL"
        );
    }

    // -----------------------------------------------------------------------
    // Property 5d — inclusion: quantity > 0 AND expiry_date within window
    // -----------------------------------------------------------------------

    /**
     * @dataProvider inclusionWindowProvider
     *
     * Positive case: a row with quantity > 0 and expiry_date within the window
     * MUST appear in expiring-soon results.
     *
     * Validates: Requirements 3.1, 3.2
     */
    public function testRowWithPositiveQuantityAndExpiryWithinWindowIsIncluded(
        int $quantity,
        string $expiryDate
    ): void {
        $this->seedWarehouse();
        $this->seedProduct('p-1');
        $this->seedStock('s-1', 'p-1', $quantity, $expiryDate);

        $ids = $this->getExpiringSoonIds(60);

        $this->assertContains(
            's-1',
            $ids,
            "Expected expiry alert for quantity=$quantity with expiry_date=$expiryDate (within 60-day window)"
        );
    }

    /** @return array<string, array{int, string}> */
    public static function inclusionWindowProvider(): array
    {
        $today = new \DateTimeImmutable('today');

        $cases = [];

        foreach ([1, 5, 10, 100] as $q) {
            $cases["qty=$q,expiry_today"]      = [$q, $today->format('Y-m-d')];
            $cases["qty=$q,expiry_in_30_days"] = [$q, $today->modify('+30 days')->format('Y-m-d')];
            $cases["qty=$q,expiry_in_60_days"] = [$q, $today->modify('+60 days')->format('Y-m-d')];
            $cases["qty=$q,already_expired"]   = [$q, $today->modify('-1 day')->format('Y-m-d')];
        }

        return $cases;
    }

    // -----------------------------------------------------------------------
    // Property 5e — mixed batch: correct inclusion/exclusion across many rows
    // -----------------------------------------------------------------------

    /**
     * Seed a large random set of rows with varying quantity and expiry_date values.
     * Assert that getExpiringSoon() returns exactly the rows satisfying:
     *   expiry_date IS NOT NULL AND expiry_date <= NOW() + 60 days AND quantity > 0
     *
     * Validates: Requirements 3.3, 3.4
     */
    public function testExpiryExclusionHoldsAcrossRandomBatch(): void
    {
        $this->seedWarehouse();

        mt_srand(2024);

        $today = new \DateTimeImmutable('today');

        // Build a set of test cases covering all combinations of the two exclusion conditions
        $rows = [];

        // Rows that should be EXCLUDED (quantity=0 or expiry_date=null)
        for ($i = 0; $i < 15; $i++) {
            // quantity=0, expiry within window
            $rows[] = [
                'id'          => "s-zero-qty-$i",
                'quantity'    => 0,
                'expiry_date' => $today->modify('+' . mt_rand(0, 60) . ' days')->format('Y-m-d'),
                'should_include' => false,
            ];
        }

        for ($i = 0; $i < 15; $i++) {
            // quantity>0, expiry_date=null
            $rows[] = [
                'id'          => "s-null-expiry-$i",
                'quantity'    => mt_rand(1, 200),
                'expiry_date' => null,
                'should_include' => false,
            ];
        }

        for ($i = 0; $i < 5; $i++) {
            // quantity=0, expiry_date=null
            $rows[] = [
                'id'          => "s-both-zero-$i",
                'quantity'    => 0,
                'expiry_date' => null,
                'should_include' => false,
            ];
        }

        // Rows that should be INCLUDED (quantity>0, expiry within window)
        for ($i = 0; $i < 15; $i++) {
            $rows[] = [
                'id'          => "s-include-$i",
                'quantity'    => mt_rand(1, 200),
                'expiry_date' => $today->modify('+' . mt_rand(0, 60) . ' days')->format('Y-m-d'),
                'should_include' => true,
            ];
        }

        // Rows that should be EXCLUDED (quantity>0, expiry outside window)
        for ($i = 0; $i < 10; $i++) {
            $rows[] = [
                'id'          => "s-outside-window-$i",
                'quantity'    => mt_rand(1, 200),
                'expiry_date' => $today->modify('+' . mt_rand(61, 365) . ' days')->format('Y-m-d'),
                'should_include' => false,
            ];
        }

        $expectedIds    = [];
        $notExpectedIds = [];

        foreach ($rows as $idx => $row) {
            $productId = "p-$idx";
            $this->seedProduct($productId);
            $this->seedStock($row['id'], $productId, $row['quantity'], $row['expiry_date']);

            if ($row['should_include']) {
                $expectedIds[] = $row['id'];
            } else {
                $notExpectedIds[] = $row['id'];
            }
        }

        $returnedIds = $this->getExpiringSoonIds(60);

        foreach ($expectedIds as $id) {
            $this->assertContains(
                $id,
                $returnedIds,
                "Stock row $id should be in expiring-soon results (quantity>0 and expiry within window)"
            );
        }

        foreach ($notExpectedIds as $id) {
            $this->assertNotContains(
                $id,
                $returnedIds,
                "Stock row $id should NOT be in expiring-soon results (quantity=0 or expiry_date=null or outside window)"
            );
        }
    }
}
