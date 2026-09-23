<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Services\SaleService;
use App\Models\SalesInvoice;
use PDO;

/**
 * Bug Condition Exploration Test - POS Sales Missing Invoice Generation
 *
 * **Validates: Requirements 2.1, 2.2, 2.3**
 *
 * **CRITICAL**: This test MUST FAIL on unfixed code - failure confirms the bug exists
 *
 * Property 1: Bug Condition - POS Sales Missing Invoice Generation
 *   For any sale transaction where the sale is successfully created through the POS system
 *   (source = "POS", status = "completed"), an invoice SHOULD be automatically generated
 *   containing all transaction details.
 *
 * **EXPECTED OUTCOME ON UNFIXED CODE**: Test FAILS (this is correct - it proves the bug exists)
 *
 * **Counterexamples to document**:
 *   - Sale record exists in sales table with receipt number
 *   - No corresponding invoice record in sales_invoices table
 *   - Query `SELECT * FROM sales_invoices WHERE sale_id = '{sale_id}'` returns empty
 *
 * Uses MySQL with temporary tables so no persistent data is written.
 * Temporary tables are session-scoped and dropped automatically on disconnect.
 */
class SaleServicePOSInvoiceGenerationBugTest extends TestCase
{
    private SaleService $service;

    // -----------------------------------------------------------------------
    // Setup / teardown
    // -----------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = $this->getMysqlConnection();
        $this->createTempSchema();
        $this->service = new SaleService();
    }

    protected function tearDown(): void
    {
        if ($this->db !== null) {
            // Drop temporary tables explicitly
            $this->db->exec("DROP TEMPORARY TABLE IF EXISTS sales");
            $this->db->exec("DROP TEMPORARY TABLE IF EXISTS sale_items");
            $this->db->exec("DROP TEMPORARY TABLE IF EXISTS sales_invoices");
            $this->db->exec("DROP TEMPORARY TABLE IF EXISTS sales_invoice_items");
            $this->db->exec("DROP TEMPORARY TABLE IF EXISTS products");
            $this->db->exec("DROP TEMPORARY TABLE IF EXISTS stock");
            $this->db->exec("DROP TEMPORARY TABLE IF EXISTS store_stock");
            $this->db->exec("DROP TEMPORARY TABLE IF EXISTS customers");
            $this->db->exec("DROP TEMPORARY TABLE IF EXISTS loyalty_tiers");
            $this->db->exec("DROP TEMPORARY TABLE IF EXISTS loyalty_transactions");
            $this->db->exec("DROP TEMPORARY TABLE IF EXISTS users");
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

    /**
     * Create TEMPORARY tables that shadow the real ones for this session only.
     */
    private function createTempSchema(): void
    {
        // Sales table
        $this->db->exec("
            CREATE TEMPORARY TABLE sales (
                id VARCHAR(36) NOT NULL PRIMARY KEY,
                store_id VARCHAR(36) NOT NULL,
                store_type VARCHAR(50) NOT NULL,
                subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
                discount DECIMAL(10,2) NOT NULL DEFAULT 0,
                tax DECIMAL(10,2) NOT NULL DEFAULT 0,
                total DECIMAL(10,2) NOT NULL,
                payment_method VARCHAR(50) DEFAULT 'cash',
                cashier_id VARCHAR(36) DEFAULT NULL,
                customer_id VARCHAR(36) DEFAULT NULL,
                receipt_no VARCHAR(100) NOT NULL,
                is_wholesale TINYINT(1) NOT NULL DEFAULT 0,
                is_refund TINYINT(1) NOT NULL DEFAULT 0,
                original_sale_id VARCHAR(36) DEFAULT NULL,
                note TEXT DEFAULT NULL,
                sale_date DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=MEMORY
        ");

        // Sale items table
        $this->db->exec("
            CREATE TEMPORARY TABLE sale_items (
                id VARCHAR(36) NOT NULL PRIMARY KEY,
                sale_id VARCHAR(36) NOT NULL,
                product_id VARCHAR(36) NOT NULL,
                product_name VARCHAR(255) DEFAULT NULL,
                quantity INT NOT NULL,
                unit_price DECIMAL(10,2) NOT NULL,
                discount DECIMAL(10,2) NOT NULL DEFAULT 0,
                total DECIMAL(10,2) NOT NULL,
                batch_number VARCHAR(100) DEFAULT NULL,
                expiry_date DATE DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=MEMORY
        ");

        // Sales invoices table
        $this->db->exec("
            CREATE TEMPORARY TABLE sales_invoices (
                id VARCHAR(36) NOT NULL PRIMARY KEY,
                invoice_no VARCHAR(100) NOT NULL,
                sale_id VARCHAR(36) DEFAULT NULL,
                sales_order_id VARCHAR(36) DEFAULT NULL,
                customer_id VARCHAR(36) DEFAULT NULL,
                customer_name VARCHAR(255) DEFAULT NULL,
                customer_email VARCHAR(255) DEFAULT NULL,
                customer_phone VARCHAR(50) DEFAULT NULL,
                customer_address TEXT DEFAULT NULL,
                store_id VARCHAR(36) DEFAULT NULL,
                store_type VARCHAR(50) NOT NULL,
                invoice_date DATE NOT NULL,
                due_date DATE DEFAULT NULL,
                subtotal DECIMAL(10,2) NOT NULL,
                discount DECIMAL(10,2) NOT NULL DEFAULT 0,
                tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
                tax DECIMAL(10,2) NOT NULL DEFAULT 0,
                total DECIMAL(10,2) NOT NULL,
                amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0,
                amount_due DECIMAL(10,2) NOT NULL DEFAULT 0,
                status VARCHAR(50) NOT NULL DEFAULT 'unpaid',
                notes TEXT DEFAULT NULL,
                internal_notes TEXT DEFAULT NULL,
                terms TEXT DEFAULT NULL,
                created_by VARCHAR(36) DEFAULT NULL,
                created_by_name VARCHAR(255) DEFAULT NULL,
                paid_date DATETIME DEFAULT NULL,
                void_reason TEXT DEFAULT NULL,
                voided_by VARCHAR(36) DEFAULT NULL,
                voided_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=MEMORY
        ");

        // Sales invoice items table
        $this->db->exec("
            CREATE TEMPORARY TABLE sales_invoice_items (
                id VARCHAR(36) NOT NULL PRIMARY KEY,
                invoice_id VARCHAR(36) NOT NULL,
                product_id VARCHAR(36) NOT NULL,
                product_name VARCHAR(255) NOT NULL,
                product_sku VARCHAR(100) DEFAULT NULL,
                quantity DECIMAL(10,2) NOT NULL,
                unit VARCHAR(50) NOT NULL DEFAULT 'pcs',
                unit_price DECIMAL(10,2) NOT NULL,
                discount DECIMAL(10,2) NOT NULL DEFAULT 0,
                tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
                tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                line_total DECIMAL(10,2) NOT NULL,
                notes TEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=MEMORY
        ");

        // Products table
        $this->db->exec("
            CREATE TEMPORARY TABLE products (
                id VARCHAR(36) NOT NULL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                sku VARCHAR(100) NOT NULL,
                stock INT NOT NULL DEFAULT 0,
                min_stock INT NOT NULL DEFAULT 0,
                unit VARCHAR(50) NOT NULL DEFAULT 'pcs',
                store_type VARCHAR(50) NOT NULL DEFAULT 'supermarket',
                is_active TINYINT(1) NOT NULL DEFAULT 1
            ) ENGINE=MEMORY
        ");

        // Stock table
        $this->db->exec("
            CREATE TEMPORARY TABLE stock (
                id VARCHAR(36) NOT NULL PRIMARY KEY,
                product_id VARCHAR(36) NOT NULL,
                warehouse_id VARCHAR(36) NOT NULL,
                batch_number VARCHAR(100) DEFAULT NULL,
                expiry_date DATE DEFAULT NULL,
                quantity INT NOT NULL DEFAULT 0
            ) ENGINE=MEMORY
        ");

        // Store stock table
        $this->db->exec("
            CREATE TEMPORARY TABLE store_stock (
                id VARCHAR(36) NOT NULL PRIMARY KEY,
                store_id VARCHAR(36) NOT NULL,
                product_id VARCHAR(36) NOT NULL,
                batch_number VARCHAR(100) DEFAULT NULL,
                expiry_date DATE DEFAULT NULL,
                quantity INT NOT NULL DEFAULT 0
            ) ENGINE=MEMORY
        ");

        // Customers table
        $this->db->exec("
            CREATE TEMPORARY TABLE customers (
                id VARCHAR(36) NOT NULL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) DEFAULT NULL,
                phone VARCHAR(50) DEFAULT NULL,
                address TEXT DEFAULT NULL,
                total_purchases DECIMAL(10,2) NOT NULL DEFAULT 0,
                loyalty_points INT NOT NULL DEFAULT 0,
                loyalty_tier VARCHAR(50) NOT NULL DEFAULT 'bronze'
            ) ENGINE=MEMORY
        ");

        // Loyalty tiers table
        $this->db->exec("
            CREATE TEMPORARY TABLE loyalty_tiers (
                id VARCHAR(36) NOT NULL PRIMARY KEY,
                slug VARCHAR(50) NOT NULL,
                name VARCHAR(100) NOT NULL,
                min_points INT NOT NULL DEFAULT 0,
                points_rate DECIMAL(5,2) NOT NULL DEFAULT 1.0
            ) ENGINE=MEMORY
        ");

        // Loyalty transactions table
        $this->db->exec("
            CREATE TEMPORARY TABLE loyalty_transactions (
                id VARCHAR(36) NOT NULL PRIMARY KEY,
                customer_id VARCHAR(36) NOT NULL,
                type VARCHAR(50) NOT NULL,
                points INT NOT NULL,
                balance INT NOT NULL,
                reference VARCHAR(100) DEFAULT NULL,
                note TEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=MEMORY
        ");

        // Users table
        $this->db->exec("
            CREATE TEMPORARY TABLE users (
                id VARCHAR(36) NOT NULL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL
            ) ENGINE=MEMORY
        ");

        // Seed default loyalty tier
        $this->db->exec("
            INSERT INTO loyalty_tiers (id, slug, name, min_points, points_rate)
            VALUES ('tier-bronze', 'bronze', 'Bronze', 0, 1.0)
        ");
    }

    // -----------------------------------------------------------------------
    // Seed helpers
    // -----------------------------------------------------------------------

    private function seedProduct(
        string $id,
        string $name,
        int $stock = 100,
        string $storeType = 'supermarket'
    ): void {
        $this->db->prepare("
            INSERT INTO products (id, name, sku, stock, min_stock, unit, store_type, is_active)
            VALUES (:id, :name, :sku, :stock, 10, 'pcs', :store_type, 1)
        ")->execute([
            'id'         => $id,
            'name'       => $name,
            'sku'        => "SKU-$id",
            'stock'      => $stock,
            'store_type' => $storeType,
        ]);
    }

    private function seedStoreStock(
        string $storeId,
        string $productId,
        int $quantity
    ): void {
        $this->db->prepare("
            INSERT INTO store_stock (id, store_id, product_id, batch_number, expiry_date, quantity)
            VALUES (:id, :store_id, :product_id, NULL, NULL, :quantity)
        ")->execute([
            'id'         => 'ss-' . uniqid(),
            'store_id'   => $storeId,
            'product_id' => $productId,
            'quantity'   => $quantity,
        ]);
    }

    private function seedCustomer(
        string $id,
        string $name,
        ?string $email = null,
        ?string $phone = null
    ): void {
        $this->db->prepare("
            INSERT INTO customers (id, name, email, phone, total_purchases, loyalty_points, loyalty_tier)
            VALUES (:id, :name, :email, :phone, 0, 0, 'bronze')
        ")->execute([
            'id'    => $id,
            'name'  => $name,
            'email' => $email,
            'phone' => $phone,
        ]);
    }

    private function seedUser(string $id, string $name, string $email): void
    {
        $this->db->prepare("
            INSERT INTO users (id, name, email)
            VALUES (:id, :name, :email)
        ")->execute([
            'id'    => $id,
            'name'  => $name,
            'email' => $email,
        ]);
    }

    /**
     * Check if an invoice exists for a given sale_id
     */
    private function invoiceExistsForSale(string $saleId): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM sales_invoices WHERE sale_id = :sale_id");
        $stmt->execute(['sale_id' => $saleId]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Get invoice for a given sale_id
     */
    private function getInvoiceForSale(string $saleId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM sales_invoices WHERE sale_id = :sale_id LIMIT 1");
        $stmt->execute(['sale_id' => $saleId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        return $invoice ?: null;
    }

    /**
     * Get invoice items for a given invoice_id
     */
    private function getInvoiceItems(string $invoiceId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM sales_invoice_items WHERE invoice_id = :invoice_id");
        $stmt->execute(['invoice_id' => $invoiceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // -----------------------------------------------------------------------
    // Bug Condition Tests - These SHOULD FAIL on unfixed code
    // -----------------------------------------------------------------------

    /**
     * Test 1: Supermarket POS Sale - Invoice Should Be Generated
     *
     * **Validates: Requirements 2.1, 2.2, 2.3**
     *
     * Creates a simple supermarket POS sale and verifies that an invoice is automatically created.
     * On UNFIXED code, this test will FAIL because no invoice is generated.
     */
    public function testSupermarketPOSSaleGeneratesInvoice(): void
    {
        // Seed test data
        $this->seedProduct('p-1', 'Test Product 1', 100, 'supermarket');
        $this->seedStoreStock('store-sm-1', 'p-1', 100);

        // Create a POS sale
        $saleData = [
            'store_id'   => 'store-sm-1',
            'store_type' => 'supermarket',
            'subtotal'   => 45.00,
            'discount'   => 0,
            'tax'        => 5.00,
            'total'      => 50.00,
            'payment_method' => 'cash',
            'items'      => [
                [
                    'product_id'   => 'p-1',
                    'product_name' => 'Test Product 1',
                    'quantity'     => 2,
                    'unit_price'   => 25.00,
                    'discount'     => 0,
                    'total'        => 50.00,
                ],
            ],
        ];

        $result = $this->service->create($saleData);

        // Verify sale was created successfully
        $this->assertTrue($result['success'], 'Sale creation should succeed');
        $this->assertArrayHasKey('data', $result);
        $saleId = $result['data']['id'];

        // Verify sale exists in database
        $stmt = $this->db->prepare("SELECT * FROM sales WHERE id = :id");
        $stmt->execute(['id' => $saleId]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotNull($sale, "Sale record should exist in sales table");
        $this->assertEquals('supermarket', $sale['store_type']);
        $this->assertEquals(50.00, $sale['total']);

        // **BUG CONDITION CHECK**: Invoice should exist for this POS sale
        // On UNFIXED code, this assertion will FAIL (no invoice exists)
        $this->assertTrue(
            $this->invoiceExistsForSale($saleId),
            "COUNTEREXAMPLE FOUND: Sale {$sale['receipt_no']} (ID: $saleId) exists but NO invoice was generated. " .
            "Query: SELECT * FROM sales_invoices WHERE sale_id = '$saleId' returns empty."
        );

        // If invoice exists, verify it contains all transaction details
        $invoice = $this->getInvoiceForSale($saleId);
        $this->assertNotNull($invoice, "Invoice should be retrievable");
        $this->assertEquals($saleId, $invoice['sale_id'], "Invoice should be linked to sale");
        $this->assertEquals('supermarket', $invoice['store_type']);
        $this->assertEquals(50.00, $invoice['total'], "Invoice total should match sale total");
        $this->assertEquals('paid', $invoice['status'], "Invoice status should be 'paid' for POS sales");
        $this->assertEquals(50.00, $invoice['amount_paid'], "Invoice amount_paid should equal sale total");
        $this->assertEquals(0.00, $invoice['amount_due'], "Invoice amount_due should be 0 for POS sales");

        // Verify invoice items match sale items
        $invoiceItems = $this->getInvoiceItems($invoice['id']);
        $this->assertCount(1, $invoiceItems, "Invoice should have 1 item");
        $this->assertEquals('p-1', $invoiceItems[0]['product_id']);
        $this->assertEquals(2, $invoiceItems[0]['quantity']);
        $this->assertEquals(25.00, $invoiceItems[0]['unit_price']);
    }

    /**
     * Test 2: Pharmacy POS Sale with Customer - Invoice Should Include Customer Details
     *
     * **Validates: Requirements 2.1, 2.2, 2.3**
     *
     * Creates a pharmacy POS sale with customer information and verifies invoice generation
     * with customer details included.
     */
    public function testPharmacyPOSSaleWithCustomerGeneratesInvoiceWithCustomerDetails(): void
    {
        // Seed test data
        $this->seedProduct('p-2', 'Medicine A', 50, 'pharmacy');
        $this->seedStoreStock('store-ph-1', 'p-2', 50);
        $this->seedCustomer('c-1', 'John Doe', 'john@example.com', '555-1234');

        // Create a POS sale with customer
        $saleData = [
            'store_id'   => 'store-ph-1',
            'store_type' => 'pharmacy',
            'customer_id' => 'c-1',
            'subtotal'   => 90.00,
            'discount'   => 10.00,
            'tax'        => 10.00,
            'total'      => 90.00,
            'payment_method' => 'card',
            'items'      => [
                [
                    'product_id'   => 'p-2',
                    'product_name' => 'Medicine A',
                    'quantity'     => 3,
                    'unit_price'   => 30.00,
                    'discount'     => 0,
                    'total'        => 90.00,
                ],
            ],
        ];

        $result = $this->service->create($saleData);

        // Verify sale was created successfully
        $this->assertTrue($result['success']);
        $saleId = $result['data']['id'];

        // **BUG CONDITION CHECK**: Invoice should exist
        $this->assertTrue(
            $this->invoiceExistsForSale($saleId),
            "COUNTEREXAMPLE FOUND: Pharmacy sale with customer exists but NO invoice was generated."
        );

        // Verify invoice contains customer details
        $invoice = $this->getInvoiceForSale($saleId);
        $this->assertNotNull($invoice);
        $this->assertEquals('c-1', $invoice['customer_id'], "Invoice should include customer_id");
        $this->assertEquals('John Doe', $invoice['customer_name'], "Invoice should include customer name");
        $this->assertEquals('john@example.com', $invoice['customer_email'], "Invoice should include customer email");
        $this->assertEquals('555-1234', $invoice['customer_phone'], "Invoice should include customer phone");
    }

    /**
     * Test 3: Hardware POS Sale with Multiple Items - Invoice Items Should Match Sale Items
     *
     * **Validates: Requirements 2.1, 2.2, 2.3**
     *
     * Creates a hardware store POS sale with multiple items and verifies all items
     * are correctly included in the invoice.
     */
    public function testHardwarePOSSaleWithMultipleItemsGeneratesCompleteInvoice(): void
    {
        // Seed test data
        $this->seedProduct('p-3', 'Hammer', 20, 'hardware');
        $this->seedProduct('p-4', 'Nails Box', 50, 'hardware');
        $this->seedProduct('p-5', 'Paint Bucket', 30, 'hardware');
        $this->seedStoreStock('store-hw-1', 'p-3', 20);
        $this->seedStoreStock('store-hw-1', 'p-4', 50);
        $this->seedStoreStock('store-hw-1', 'p-5', 30);

        // Create a POS sale with multiple items
        $saleData = [
            'store_id'   => 'store-hw-1',
            'store_type' => 'hardware',
            'subtotal'   => 110.00,
            'discount'   => 10.00,
            'tax'        => 10.00,
            'total'      => 110.00,
            'payment_method' => 'cash',
            'items'      => [
                [
                    'product_id'   => 'p-3',
                    'product_name' => 'Hammer',
                    'quantity'     => 1,
                    'unit_price'   => 25.00,
                    'discount'     => 0,
                    'total'        => 25.00,
                ],
                [
                    'product_id'   => 'p-4',
                    'product_name' => 'Nails Box',
                    'quantity'     => 2,
                    'unit_price'   => 15.00,
                    'discount'     => 0,
                    'total'        => 30.00,
                ],
                [
                    'product_id'   => 'p-5',
                    'product_name' => 'Paint Bucket',
                    'quantity'     => 1,
                    'unit_price'   => 55.00,
                    'discount'     => 0,
                    'total'        => 55.00,
                ],
            ],
        ];

        $result = $this->service->create($saleData);

        // Verify sale was created successfully
        $this->assertTrue($result['success']);
        $saleId = $result['data']['id'];

        // **BUG CONDITION CHECK**: Invoice should exist
        $this->assertTrue(
            $this->invoiceExistsForSale($saleId),
            "COUNTEREXAMPLE FOUND: Hardware sale with 3 items exists but NO invoice was generated."
        );

        // Verify invoice items match sale items
        $invoice = $this->getInvoiceForSale($saleId);
        $invoiceItems = $this->getInvoiceItems($invoice['id']);
        $this->assertCount(3, $invoiceItems, "Invoice should have 3 items matching sale items");

        // Verify each item
        $itemsByProductId = [];
        foreach ($invoiceItems as $item) {
            $itemsByProductId[$item['product_id']] = $item;
        }

        $this->assertArrayHasKey('p-3', $itemsByProductId);
        $this->assertEquals(1, $itemsByProductId['p-3']['quantity']);
        $this->assertEquals(25.00, $itemsByProductId['p-3']['unit_price']);

        $this->assertArrayHasKey('p-4', $itemsByProductId);
        $this->assertEquals(2, $itemsByProductId['p-4']['quantity']);
        $this->assertEquals(15.00, $itemsByProductId['p-4']['unit_price']);

        $this->assertArrayHasKey('p-5', $itemsByProductId);
        $this->assertEquals(1, $itemsByProductId['p-5']['quantity']);
        $this->assertEquals(55.00, $itemsByProductId['p-5']['unit_price']);
    }

    /**
     * Test 4: Property-Based Test - Random POS Sales Should Generate Invoices
     *
     * **Validates: Requirements 2.1, 2.2, 2.3**
     *
     * @dataProvider randomPOSSalesProvider
     *
     * Generates random POS sale configurations and verifies that invoices are created
     * for all completed POS sales.
     */
    public function testRandomPOSSalesGenerateInvoices(
        string $storeType,
        int $itemCount,
        float $total
    ): void {
        $storeId = "store-$storeType-test";

        // Seed products
        for ($i = 1; $i <= $itemCount; $i++) {
            $productId = "p-rand-$i";
            $this->seedProduct($productId, "Product $i", 1000, $storeType);
            $this->seedStoreStock($storeId, $productId, 1000);
        }

        // Create sale items
        $items = [];
        $itemTotal = $total / $itemCount;
        for ($i = 1; $i <= $itemCount; $i++) {
            $items[] = [
                'product_id'   => "p-rand-$i",
                'product_name' => "Product $i",
                'quantity'     => 1,
                'unit_price'   => $itemTotal,
                'discount'     => 0,
                'total'        => $itemTotal,
            ];
        }

        // Create POS sale
        $saleData = [
            'store_id'   => $storeId,
            'store_type' => $storeType,
            'subtotal'   => $total,
            'discount'   => 0,
            'tax'        => 0,
            'total'      => $total,
            'payment_method' => 'cash',
            'items'      => $items,
        ];

        $result = $this->service->create($saleData);

        // Verify sale was created
        $this->assertTrue($result['success']);
        $saleId = $result['data']['id'];

        // **BUG CONDITION CHECK**: Invoice should exist for all POS sales
        $this->assertTrue(
            $this->invoiceExistsForSale($saleId),
            "COUNTEREXAMPLE FOUND: POS sale (store_type=$storeType, items=$itemCount, total=$total) " .
            "exists but NO invoice was generated."
        );

        // Verify invoice correctness
        $invoice = $this->getInvoiceForSale($saleId);
        $this->assertEquals($total, $invoice['total']);
        $this->assertEquals('paid', $invoice['status']);
        $this->assertEquals($total, $invoice['amount_paid']);

        $invoiceItems = $this->getInvoiceItems($invoice['id']);
        $this->assertCount($itemCount, $invoiceItems, "Invoice should have $itemCount items");
    }

    /**
     * Data provider for random POS sales
     *
     * Generates various combinations of store types, item counts, and totals
     * to test the property across different scenarios.
     */
    public static function randomPOSSalesProvider(): array
    {
        $cases = [];

        mt_srand(42);

        $storeTypes = ['supermarket', 'pharmacy', 'hardware'];

        // Boundary cases
        foreach ($storeTypes as $type) {
            $cases["single_item_$type"] = [$type, 1, 10.00];
            $cases["five_items_$type"] = [$type, 5, 100.00];
        }

        // Random cases
        for ($i = 0; $i < 15; $i++) {
            $storeType = $storeTypes[array_rand($storeTypes)];
            $itemCount = mt_rand(1, 10);
            $total = mt_rand(10, 500) + (mt_rand(0, 99) / 100);
            $cases["random_$i"] = [$storeType, $itemCount, $total];
        }

        return $cases;
    }
}
