<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\WarehouseAlertService;
use PDO;

/**
 * Property test for alert row shape (Property 4).
 *
 * Property 4: Alert rows contain required warehouse fields
 *   For any alert row returned by WarehouseAlertService, assert all required
 *   fields are present: stock_id, product_id, product_name, sku, warehouse_id,
 *   warehouse_name, batch_number, expiry_date, quantity, min_stock, unit,
 *   store_type, alert_type.
 *
 * Validates: Requirements 2.7, 3.7
 *
 * Uses MySQL with temporary tables so no persistent data is written.
 * Temporary tables are session-scoped and dropped automatically on disconnect.
 */
class WarehouseAlertServiceRowShapePropertyTest extends TestCase
{
    private const REQUIRED_FIELDS = [
        'stock_id',
        'product_id',
        'product_name',
        'sku',
        'warehouse_id',
        'warehouse_name',
        'batch_number',
        'expiry_date',
        'quantity',
        'min_stock',
        'unit',
        'store_type',
        'alert_type',
    ];

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

    private function seedProduct(
        string $id,
        int $minStock = 10,
        string $storeType = 'supermarket',
        string $unit = 'pcs'
    ): void {
        $this->db->prepare("
            INSERT INTO products (id, name, sku, min_stock, unit, store_type, is_active)
            VALUES (:id, :name, :sku, :min_stock, :unit, :store_type, 1)
        ")->execute([
            'id'         => $id,
            'name'       => "Product $id",
            'sku'        => "SKU-$id",
            'min_stock'  => $minStock,
            'unit'       => $unit,
            'store_type' => $storeType,
        ]);
    }

    private function seedStock(
        string $stockId,
        string $productId,
        int $quantity,
        ?string $batchNumber = null,
        ?string $expiryDate = null,
        string $warehouseId = 'wh-1'
    ): void {
        $this->db->prepare("
            INSERT INTO stock (id, product_id, warehouse_id, batch_number, expiry_date, quantity)
            VALUES (:id, :product_id, :warehouse_id, :batch_number, :expiry_date, :quantity)
        ")->execute([
            'id'           => $stockId,
            'product_id'   => $productId,
            'warehouse_id' => $warehouseId,
            'batch_number' => $batchNumber,
            'expiry_date'  => $expiryDate,
            'quantity'     => $quantity,
        ]);
    }

    // -----------------------------------------------------------------------
    // Assertion helper
    // -----------------------------------------------------------------------

    private function assertRowHasRequiredFields(array $row, string $context = ''): void
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            $this->assertArrayHasKey(
                $field,
                $row,
                "Alert row is missing required field '$field'" . ($context ? " ($context)" : '')
            );
        }
    }

    // -----------------------------------------------------------------------
    // Property 4a — getLowStock() rows contain all 13 required fields
    // -----------------------------------------------------------------------

    /**
     * Every row returned by getLowStock() must contain all 13 required fields.
     *
     * Validates: Requirement 2.7
     */
    public function testGetLowStockRowsContainAllRequiredFields(): void
    {
        $this->seedWarehouse('wh-1', 'Main Warehouse');
        $this->seedProduct('p-1', 10, 'supermarket', 'pcs');
        $this->seedProduct('p-2', 5, 'pharmacy', 'box');
        $this->seedProduct('p-3', 20, 'hardware', 'kg');

        // All three rows qualify as low-stock (quantity <= min_stock)
        $this->seedStock('s-1', 'p-1', 5,  'BATCH-A', '2025-12-31');
        $this->seedStock('s-2', 'p-2', 3,  null,      null);
        $this->seedStock('s-3', 'p-3', 20, 'BATCH-C', null);

        $rows = $this->service->getLowStock();

        $this->assertNotEmpty($rows, 'getLowStock() should return at least one row');

        foreach ($rows as $index => $row) {
            $this->assertRowHasRequiredFields($row, "row index $index");
        }
    }

    /**
     * @dataProvider lowStockVariantsProvider
     *
     * getLowStock() rows have all required fields across varied product/stock configurations.
     *
     * Validates: Requirement 2.7
     */
    public function testGetLowStockRowShapeAcrossVariants(
        int $quantity,
        int $minStock,
        ?string $batchNumber,
        ?string $expiryDate,
        string $storeType,
        string $unit
    ): void {
        $this->seedWarehouse();
        $this->seedProduct('p-1', $minStock, $storeType, $unit);
        $this->seedStock('s-1', 'p-1', $quantity, $batchNumber, $expiryDate);

        $rows = $this->service->getLowStock();

        $this->assertNotEmpty($rows, "Expected a low-stock row for quantity=$quantity, min_stock=$minStock");

        foreach ($rows as $index => $row) {
            $this->assertRowHasRequiredFields($row, "row index $index");
        }
    }

    /** @return array<string, array{int, int, string|null, string|null, string, string}> */
    public static function lowStockVariantsProvider(): array
    {
        $today = new \DateTimeImmutable('today');

        return [
            'with_batch_and_expiry'    => [5,  10, 'BATCH-001', $today->modify('+30 days')->format('Y-m-d'), 'supermarket', 'pcs'],
            'with_batch_no_expiry'     => [3,  10, 'BATCH-002', null,                                        'pharmacy',    'box'],
            'no_batch_with_expiry'     => [0,  10, null,        $today->modify('+60 days')->format('Y-m-d'), 'hardware',    'kg'],
            'no_batch_no_expiry'       => [1,  5,  null,        null,                                        'supermarket', 'unit'],
            'quantity_equals_min'      => [10, 10, 'BATCH-003', null,                                        'pharmacy',    'pcs'],
            'quantity_zero'            => [0,  1,  null,        null,                                        'hardware',    'litre'],
            'large_values'             => [99, 100, 'BATCH-999', $today->modify('+90 days')->format('Y-m-d'), 'supermarket', 'carton'],
        ];
    }

    // -----------------------------------------------------------------------
    // Property 4b — getExpiringSoon() rows contain all 13 required fields
    // -----------------------------------------------------------------------

    /**
     * Every row returned by getExpiringSoon() must contain all 13 required fields.
     *
     * Validates: Requirement 3.7
     */
    public function testGetExpiringSoonRowsContainAllRequiredFields(): void
    {
        $today = new \DateTimeImmutable('today');

        $this->seedWarehouse('wh-1', 'Main Warehouse');
        $this->seedProduct('p-1', 10, 'supermarket', 'pcs');
        $this->seedProduct('p-2', 5,  'pharmacy',    'box');
        $this->seedProduct('p-3', 20, 'hardware',    'kg');

        // All three rows qualify as expiring-soon (quantity > 0, expiry within 60 days)
        $this->seedStock('s-1', 'p-1', 5,  'BATCH-A', $today->modify('+10 days')->format('Y-m-d'));
        $this->seedStock('s-2', 'p-2', 3,  null,      $today->modify('+30 days')->format('Y-m-d'));
        $this->seedStock('s-3', 'p-3', 20, 'BATCH-C', $today->modify('+59 days')->format('Y-m-d'));

        $rows = $this->service->getExpiringSoon(60);

        $this->assertNotEmpty($rows, 'getExpiringSoon() should return at least one row');

        foreach ($rows as $index => $row) {
            $this->assertRowHasRequiredFields($row, "row index $index");
        }
    }

    /**
     * @dataProvider expiringSoonVariantsProvider
     *
     * getExpiringSoon() rows have all required fields across varied configurations.
     *
     * Validates: Requirement 3.7
     */
    public function testGetExpiringSoonRowShapeAcrossVariants(
        int $quantity,
        string $expiryDate,
        ?string $batchNumber,
        string $storeType,
        string $unit,
        int $days
    ): void {
        $this->seedWarehouse();
        $this->seedProduct('p-1', 10, $storeType, $unit);
        $this->seedStock('s-1', 'p-1', $quantity, $batchNumber, $expiryDate);

        $rows = $this->service->getExpiringSoon($days);

        $this->assertNotEmpty($rows, "Expected an expiring-soon row for quantity=$quantity, expiry=$expiryDate, days=$days");

        foreach ($rows as $index => $row) {
            $this->assertRowHasRequiredFields($row, "row index $index");
        }
    }

    /** @return array<string, array{int, string, string|null, string, string, int}> */
    public static function expiringSoonVariantsProvider(): array
    {
        $today = new \DateTimeImmutable('today');

        return [
            'expiry_today_with_batch'    => [5,  $today->format('Y-m-d'),                          'BATCH-001', 'supermarket', 'pcs',    60],
            'expiry_in_30_days_no_batch' => [10, $today->modify('+30 days')->format('Y-m-d'),       null,        'pharmacy',    'box',    60],
            'already_expired_with_batch' => [3,  $today->modify('-5 days')->format('Y-m-d'),        'BATCH-002', 'hardware',    'kg',     60],
            'expiry_in_1_day'            => [1,  $today->modify('+1 day')->format('Y-m-d'),         null,        'supermarket', 'unit',   60],
            'expiry_at_window_boundary'  => [7,  $today->modify('+60 days')->format('Y-m-d'),       'BATCH-003', 'pharmacy',    'pcs',    60],
            'short_window_5_days'        => [2,  $today->modify('+3 days')->format('Y-m-d'),        null,        'hardware',    'litre',  5],
            'large_quantity'             => [999, $today->modify('+45 days')->format('Y-m-d'),      'BATCH-999', 'supermarket', 'carton', 60],
        ];
    }

    // -----------------------------------------------------------------------
    // Property 4c — getLowStock() rows have alert_type = 'low_stock'
    // -----------------------------------------------------------------------

    /**
     * Every row returned by getLowStock() must have alert_type = 'low_stock'.
     *
     * Validates: Requirement 2.7
     */
    public function testGetLowStockRowsHaveCorrectAlertType(): void
    {
        $this->seedWarehouse();

        mt_srand(42);

        for ($i = 0; $i < 10; $i++) {
            $minStock = mt_rand(1, 50);
            $quantity = mt_rand(0, $minStock);
            $this->seedProduct("p-$i", $minStock);
            $this->seedStock("s-$i", "p-$i", $quantity);
        }

        $rows = $this->service->getLowStock();

        $this->assertNotEmpty($rows, 'getLowStock() should return rows');

        foreach ($rows as $index => $row) {
            $this->assertSame(
                'low_stock',
                $row['alert_type'],
                "Row $index from getLowStock() must have alert_type='low_stock', got '{$row['alert_type']}'"
            );
        }
    }

    // -----------------------------------------------------------------------
    // Property 4d — getExpiringSoon() rows have alert_type = 'expiring_soon'
    // -----------------------------------------------------------------------

    /**
     * Every row returned by getExpiringSoon() must have alert_type = 'expiring_soon'.
     *
     * Validates: Requirement 3.7
     */
    public function testGetExpiringSoonRowsHaveCorrectAlertType(): void
    {
        $today = new \DateTimeImmutable('today');

        $this->seedWarehouse();

        mt_srand(42);

        for ($i = 0; $i < 10; $i++) {
            $daysOffset = mt_rand(0, 59);
            $expiryDate = $today->modify("+$daysOffset days")->format('Y-m-d');
            $quantity   = mt_rand(1, 100);
            $this->seedProduct("p-$i");
            $this->seedStock("s-$i", "p-$i", $quantity, null, $expiryDate);
        }

        $rows = $this->service->getExpiringSoon(60);

        $this->assertNotEmpty($rows, 'getExpiringSoon() should return rows');

        foreach ($rows as $index => $row) {
            $this->assertSame(
                'expiring_soon',
                $row['alert_type'],
                "Row $index from getExpiringSoon() must have alert_type='expiring_soon', got '{$row['alert_type']}'"
            );
        }
    }

    // -----------------------------------------------------------------------
    // Property 4e — mixed batch: row shape holds across many random rows
    // -----------------------------------------------------------------------

    /**
     * Seed a large random set of qualifying rows for both getLowStock() and
     * getExpiringSoon(), then assert every returned row has all 13 required fields
     * and the correct alert_type.
     *
     * Validates: Requirements 2.7, 3.7
     */
    public function testRowShapeHoldsAcrossRandomBatch(): void
    {
        $today = new \DateTimeImmutable('today');

        $this->seedWarehouse('wh-1', 'Test Warehouse');

        mt_srand(9999);

        // Seed 20 products with low-stock conditions
        for ($i = 0; $i < 20; $i++) {
            $minStock = mt_rand(1, 100);
            $quantity = mt_rand(0, $minStock);
            $this->seedProduct("p-ls-$i", $minStock, 'supermarket', 'pcs');
            $this->seedStock(
                "s-ls-$i",
                "p-ls-$i",
                $quantity,
                mt_rand(0, 1) ? "BATCH-LS-$i" : null,
                mt_rand(0, 1) ? $today->modify('+' . mt_rand(1, 365) . ' days')->format('Y-m-d') : null
            );
        }

        // Seed 20 products with expiring-soon conditions
        for ($i = 0; $i < 20; $i++) {
            $daysOffset = mt_rand(0, 60);
            $expiryDate = $today->modify("+$daysOffset days")->format('Y-m-d');
            $quantity   = mt_rand(1, 200);
            $this->seedProduct("p-es-$i", 0, 'pharmacy', 'box'); // min_stock=0 so no low-stock overlap
            $this->seedStock(
                "s-es-$i",
                "p-es-$i",
                $quantity,
                mt_rand(0, 1) ? "BATCH-ES-$i" : null,
                $expiryDate
            );
        }

        // Assert getLowStock() row shape
        $lowStockRows = $this->service->getLowStock();
        $this->assertNotEmpty($lowStockRows, 'getLowStock() should return rows from seeded data');

        foreach ($lowStockRows as $index => $row) {
            $this->assertRowHasRequiredFields($row, "getLowStock() row $index");
            $this->assertSame('low_stock', $row['alert_type'], "getLowStock() row $index must have alert_type='low_stock'");
        }

        // Assert getExpiringSoon() row shape
        $expiringSoonRows = $this->service->getExpiringSoon(60);
        $this->assertNotEmpty($expiringSoonRows, 'getExpiringSoon() should return rows from seeded data');

        foreach ($expiringSoonRows as $index => $row) {
            $this->assertRowHasRequiredFields($row, "getExpiringSoon() row $index");
            $this->assertSame('expiring_soon', $row['alert_type'], "getExpiringSoon() row $index must have alert_type='expiring_soon'");
        }
    }
}
