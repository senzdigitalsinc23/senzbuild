<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\WarehouseAlertService;
use PDO;

/**
 * Property test for low-stock alert threshold (Property 3).
 *
 * Property 3: Low-stock alert threshold
 *   For any stock row with min_stock > 0, a low-stock alert appears
 *   if and only if stock.quantity <= products.min_stock.
 *
 * Validates: Requirements 2.2, 2.3
 *
 * Uses MySQL with temporary tables so no persistent data is written.
 * Temporary tables are session-scoped and dropped automatically on disconnect.
 */
class WarehouseAlertServiceLowStockPropertyTest extends TestCase
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
            // Temporary tables are dropped automatically when the connection closes,
            // but we drop them explicitly to be safe within the same session.
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
        // Use the main DB — we only create TEMPORARY tables so nothing is persisted.
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

    /**
     * Create TEMPORARY tables that shadow the real ones for this session only.
     * The WarehouseAlertService queries these tables via the same connection.
     */
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

    private function seedProduct(
        string $id,
        int $minStock,
        string $storeType = 'supermarket'
    ): void {
        $this->db->prepare("
            INSERT INTO products (id, name, sku, min_stock, unit, store_type, is_active)
            VALUES (:id, :name, :sku, :min_stock, 'pcs', :store_type, 1)
        ")->execute([
            'id'         => $id,
            'name'       => "Product $id",
            'sku'        => "SKU-$id",
            'min_stock'  => $minStock,
            'store_type' => $storeType,
        ]);
    }

    private function seedStock(
        string $stockId,
        string $productId,
        int $quantity,
        string $warehouseId = 'wh-1'
    ): void {
        $this->db->prepare("
            INSERT INTO stock (id, product_id, warehouse_id, batch_number, expiry_date, quantity)
            VALUES (:id, :product_id, :warehouse_id, NULL, NULL, :quantity)
        ")->execute([
            'id'           => $stockId,
            'product_id'   => $productId,
            'warehouse_id' => $warehouseId,
            'quantity'     => $quantity,
        ]);
    }

    /** Collect stock_ids returned by getLowStock(). */
    private function getLowStockIds(): array
    {
        $rows = $this->service->getLowStock();
        return array_column($rows, 'stock_id');
    }

    // -----------------------------------------------------------------------
    // Property 3a — inclusion: quantity <= min_stock AND min_stock > 0
    // -----------------------------------------------------------------------

    /**
     * @dataProvider inclusionPairsProvider
     *
     * For any (quantity, min_stock) pair where min_stock > 0 and quantity <= min_stock,
     * the stock row MUST appear in getLowStock() results.
     */
    public function testLowStockAlertIncludedWhenQuantityAtOrBelowThreshold(
        int $quantity,
        int $minStock
    ): void {
        $this->seedWarehouse();
        $this->seedProduct('p-1', $minStock);
        $this->seedStock('s-1', 'p-1', $quantity);

        $ids = $this->getLowStockIds();

        $this->assertContains(
            's-1',
            $ids,
            "Expected low-stock alert when quantity=$quantity <= min_stock=$minStock"
        );
    }

    /** @return array<string, array{int, int}> */
    public static function inclusionPairsProvider(): array
    {
        $cases = [];

        mt_srand(42);

        // Boundary: quantity exactly equals min_stock
        foreach ([1, 5, 10, 50, 100, 500] as $m) {
            $cases["quantity=min_stock=$m"] = [$m, $m];
        }

        // quantity = 0 (always low-stock when min_stock > 0)
        foreach ([1, 3, 10, 100] as $m) {
            $cases["quantity=0,min_stock=$m"] = [0, $m];
        }

        // Random pairs: quantity < min_stock
        for ($i = 0; $i < 20; $i++) {
            $minStock = mt_rand(1, 200);
            $quantity = mt_rand(0, $minStock - 1);
            $cases["random_below_$i (qty=$quantity,min=$minStock)"] = [$quantity, $minStock];
        }

        return $cases;
    }

    // -----------------------------------------------------------------------
    // Property 3b — exclusion: quantity > min_stock
    // -----------------------------------------------------------------------

    /**
     * @dataProvider exclusionAboveThresholdProvider
     *
     * For any (quantity, min_stock) pair where quantity > min_stock,
     * the stock row MUST NOT appear in getLowStock() results.
     */
    public function testLowStockAlertExcludedWhenQuantityAboveThreshold(
        int $quantity,
        int $minStock
    ): void {
        $this->seedWarehouse();
        $this->seedProduct('p-1', $minStock);
        $this->seedStock('s-1', 'p-1', $quantity);

        $ids = $this->getLowStockIds();

        $this->assertNotContains(
            's-1',
            $ids,
            "Expected NO low-stock alert when quantity=$quantity > min_stock=$minStock"
        );
    }

    /** @return array<string, array{int, int}> */
    public static function exclusionAboveThresholdProvider(): array
    {
        $cases = [];

        mt_srand(42);

        // Boundary: quantity = min_stock + 1
        foreach ([1, 5, 10, 50, 100] as $m) {
            $cases["quantity=min_stock+1,min=$m"] = [$m + 1, $m];
        }

        // Random pairs: quantity > min_stock
        for ($i = 0; $i < 20; $i++) {
            $minStock = mt_rand(1, 200);
            $quantity = mt_rand($minStock + 1, $minStock + 200);
            $cases["random_above_$i (qty=$quantity,min=$minStock)"] = [$quantity, $minStock];
        }

        return $cases;
    }

    // -----------------------------------------------------------------------
    // Property 3c — exclusion: min_stock = 0 (regardless of quantity)
    // -----------------------------------------------------------------------

    /**
     * @dataProvider exclusionZeroMinStockProvider
     *
     * When min_stock = 0, no low-stock alert should appear regardless of quantity.
     */
    public function testLowStockAlertExcludedWhenMinStockIsZero(int $quantity): void
    {
        $this->seedWarehouse();
        $this->seedProduct('p-1', 0); // min_stock = 0
        $this->seedStock('s-1', 'p-1', $quantity);

        $ids = $this->getLowStockIds();

        $this->assertNotContains(
            's-1',
            $ids,
            "Expected NO low-stock alert when min_stock=0 (quantity=$quantity)"
        );
    }

    /** @return array<string, array{int}> */
    public static function exclusionZeroMinStockProvider(): array
    {
        $cases = [];

        mt_srand(42);

        $cases['quantity=0,min_stock=0'] = [0];

        foreach ([1, 5, 10, 50, 100, 500] as $q) {
            $cases["quantity=$q,min_stock=0"] = [$q];
        }

        for ($i = 0; $i < 10; $i++) {
            $q = mt_rand(0, 500);
            $cases["random_zero_min_$i (qty=$q)"] = [$q];
        }

        return $cases;
    }

    // -----------------------------------------------------------------------
    // Property 3d — mixed batch: correct inclusion/exclusion across many rows
    // -----------------------------------------------------------------------

    /**
     * Seed a large random set of (quantity, min_stock) pairs and assert that
     * getLowStock() returns exactly the rows satisfying the threshold condition.
     *
     * This is the core property test: inclusion ↔ (quantity <= min_stock AND min_stock > 0).
     */
    public function testLowStockThresholdHoldsAcrossRandomBatch(): void
    {
        $this->seedWarehouse();

        mt_srand(1337);

        $pairs = [];
        for ($i = 0; $i < 50; $i++) {
            $minStock = mt_rand(0, 100);   // includes 0
            $quantity = mt_rand(0, 150);
            $pairs[] = ['quantity' => $quantity, 'min_stock' => $minStock];
        }

        $expectedIds    = [];
        $notExpectedIds = [];

        foreach ($pairs as $idx => $pair) {
            $productId = "p-$idx";
            $stockId   = "s-$idx";

            $this->seedProduct($productId, $pair['min_stock']);
            $this->seedStock($stockId, $productId, $pair['quantity']);

            $shouldAlert = $pair['min_stock'] > 0 && $pair['quantity'] <= $pair['min_stock'];

            if ($shouldAlert) {
                $expectedIds[] = $stockId;
            } else {
                $notExpectedIds[] = $stockId;
            }
        }

        $returnedIds = $this->getLowStockIds();

        foreach ($expectedIds as $id) {
            $this->assertContains(
                $id,
                $returnedIds,
                "Stock row $id should be in low-stock results (quantity <= min_stock AND min_stock > 0)"
            );
        }

        foreach ($notExpectedIds as $id) {
            $this->assertNotContains(
                $id,
                $returnedIds,
                "Stock row $id should NOT be in low-stock results"
            );
        }
    }
}
