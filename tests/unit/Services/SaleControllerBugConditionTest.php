<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Controllers\Api\v1\SaleController;
use App\Core\Request;
use App\Core\Response;
use PDO;

/**
 * Bug Condition Exploration Test - Sales Personnel Unauthorized Access to All Transactions
 *
 * **Validates: Requirements 1.1, 1.2**
 *
 * **CRITICAL**: This test MUST FAIL on unfixed code - failure confirms the bug exists.
 * **DO NOT attempt to fix the test or the code when it fails.**
 * **NOTE**: This test encodes the expected behavior - it will validate the fix when it passes
 *           after implementation.
 *
 * Property 1: Bug Condition - Sales Personnel Transaction Isolation
 *   For any API request to GET /api/v1/sales where the authenticated user has a sales
 *   personnel role (pharmacy_sales, supermarket_sales, hardware_sales), the system SHALL
 *   automatically filter the results to include only transactions where the cashier_id
 *   matches the authenticated user's ID.
 *
 * **EXPECTED OUTCOME ON UNFIXED CODE**: Test FAILS (this is correct - it proves the bug exists)
 *
 * **Counterexamples to document**:
 *   - Mary Njeri (pharmacy_sales) receives transactions with cashier_id != 'mary-123'
 *   - Sarah Kamau (supermarket_sales) receives transactions from other supermarket cashiers
 *   - David Kipchoge (hardware_sales) sees transactions processed by other hardware sales personnel
 *
 * Uses MySQL TEMPORARY tables so no persistent data is written.
 * Temporary tables are session-scoped and dropped automatically on disconnect.
 */
class SaleControllerBugConditionTest extends TestCase
{
    protected ?PDO $db = null;

    // -----------------------------------------------------------------------
    // Setup / teardown
    // -----------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->getMysqlConnection();
        $this->createTempSchema();
        $this->seedTestData();
    }

    protected function tearDown(): void
    {
        $this->db->exec("DROP TEMPORARY TABLE IF EXISTS sales");
        $this->db->exec("DROP TEMPORARY TABLE IF EXISTS sale_items");
        $this->db->exec("DROP TEMPORARY TABLE IF EXISTS users");
        $this->db->exec("DROP TEMPORARY TABLE IF EXISTS store_stock");
        $this->db->exec("DROP TEMPORARY TABLE IF EXISTS products");
        $this->db->exec("DROP TEMPORARY TABLE IF EXISTS customers");
        $this->db->exec("DROP TEMPORARY TABLE IF EXISTS loyalty_tiers");
        $this->db->exec("DROP TEMPORARY TABLE IF EXISTS loyalty_transactions");
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
     * Uses InnoDB (not MEMORY) to support TEXT/BLOB columns.
     */
    private function createTempSchema(): void
    {
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
                sale_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB
        ");

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
                expiry_date DATE DEFAULT NULL
            ) ENGINE=InnoDB
        ");

        $this->db->exec("
            CREATE TEMPORARY TABLE users (
                id VARCHAR(36) NOT NULL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                role_id VARCHAR(50) NOT NULL DEFAULT 'cashier'
            ) ENGINE=InnoDB
        ");

        $this->db->exec("
            CREATE TEMPORARY TABLE store_stock (
                id VARCHAR(36) NOT NULL PRIMARY KEY,
                store_id VARCHAR(36) NOT NULL,
                product_id VARCHAR(36) NOT NULL,
                batch_number VARCHAR(100) DEFAULT NULL,
                expiry_date DATE DEFAULT NULL,
                quantity INT NOT NULL DEFAULT 0
            ) ENGINE=InnoDB
        ");

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
            ) ENGINE=InnoDB
        ");

        $this->db->exec("
            CREATE TEMPORARY TABLE customers (
                id VARCHAR(36) NOT NULL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) DEFAULT NULL,
                phone VARCHAR(50) DEFAULT NULL,
                address VARCHAR(500) DEFAULT NULL,
                total_purchases DECIMAL(10,2) NOT NULL DEFAULT 0,
                loyalty_points INT NOT NULL DEFAULT 0,
                loyalty_tier VARCHAR(50) NOT NULL DEFAULT 'bronze',
                customer_type VARCHAR(50) NOT NULL DEFAULT 'regular',
                is_walk_in TINYINT(1) NOT NULL DEFAULT 0
            ) ENGINE=InnoDB
        ");

        $this->db->exec("
            CREATE TEMPORARY TABLE loyalty_tiers (
                id VARCHAR(36) NOT NULL PRIMARY KEY,
                slug VARCHAR(50) NOT NULL,
                name VARCHAR(100) NOT NULL,
                min_points INT NOT NULL DEFAULT 0,
                points_rate DECIMAL(5,2) NOT NULL DEFAULT 1.0
            ) ENGINE=InnoDB
        ");

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
            ) ENGINE=InnoDB
        ");

        $this->db->exec("
            INSERT INTO loyalty_tiers (id, slug, name, min_points, points_rate)
            VALUES ('tier-bronze', 'bronze', 'Bronze', 0, 1.0)
        ");
    }

    /**
     * Seed test users and sales transactions for the bug condition tests.
     *
     * Users:
     *   - mary-123:  pharmacy_sales
     *   - sarah-456: supermarket_sales
     *   - david-789: hardware_sales
     *   - other-ph:  another pharmacy_sales cashier
     *   - other-sm:  another supermarket_sales cashier
     *   - other-hw:  another hardware_sales cashier
     *
     * Sales:
     *   - Each user has at least one transaction
     *   - Each store type has transactions from multiple cashiers
     */
    private function seedTestData(): void
    {
        // Seed users
        $users = [
            ['mary-123',  'Mary Njeri',     'mary@example.com',   'pharmacy_sales'],
            ['sarah-456', 'Sarah Kamau',    'sarah@example.com',  'supermarket_sales'],
            ['david-789', 'David Kipchoge', 'david@example.com',  'hardware_sales'],
            ['other-ph',  'Other Pharmacy', 'otherph@example.com','pharmacy_sales'],
            ['other-sm',  'Other Super',    'othersm@example.com','supermarket_sales'],
            ['other-hw',  'Other Hardware', 'otherhw@example.com','hardware_sales'],
        ];
        $uStmt = $this->db->prepare(
            "INSERT INTO users (id, name, email, role_id) VALUES (:id, :name, :email, :role_id)"
        );
        foreach ($users as [$id, $name, $email, $role]) {
            $uStmt->execute(['id' => $id, 'name' => $name, 'email' => $email, 'role_id' => $role]);
        }

        // Seed sales: each cashier gets 2 transactions
        $sales = [
            // Mary's pharmacy transactions
            ['sale-mary-1', 'store-ph-1', 'pharmacy', 100.00, 'mary-123',  'RCP-MARY001'],
            ['sale-mary-2', 'store-ph-1', 'pharmacy', 200.00, 'mary-123',  'RCP-MARY002'],
            // Other pharmacy cashier's transactions
            ['sale-oph-1',  'store-ph-1', 'pharmacy', 150.00, 'other-ph',  'RCP-OPH001'],
            ['sale-oph-2',  'store-ph-1', 'pharmacy', 250.00, 'other-ph',  'RCP-OPH002'],
            // Sarah's supermarket transactions
            ['sale-sarah-1','store-sm-1', 'supermarket', 300.00, 'sarah-456', 'RCP-SARAH001'],
            ['sale-sarah-2','store-sm-1', 'supermarket', 400.00, 'sarah-456', 'RCP-SARAH002'],
            // Other supermarket cashier's transactions
            ['sale-osm-1',  'store-sm-1', 'supermarket', 350.00, 'other-sm',  'RCP-OSM001'],
            ['sale-osm-2',  'store-sm-1', 'supermarket', 450.00, 'other-sm',  'RCP-OSM002'],
            // David's hardware transactions
            ['sale-david-1','store-hw-1', 'hardware', 500.00, 'david-789', 'RCP-DAVID001'],
            ['sale-david-2','store-hw-1', 'hardware', 600.00, 'david-789', 'RCP-DAVID002'],
            // Other hardware cashier's transactions
            ['sale-ohw-1',  'store-hw-1', 'hardware', 550.00, 'other-hw',  'RCP-OHW001'],
            ['sale-ohw-2',  'store-hw-1', 'hardware', 650.00, 'other-hw',  'RCP-OHW002'],
        ];

        $sStmt = $this->db->prepare("
            INSERT INTO sales (id, store_id, store_type, subtotal, discount, tax, total,
                payment_method, cashier_id, receipt_no, is_wholesale, is_refund, sale_date)
            VALUES (:id, :store_id, :store_type, :total, 0, 0, :total,
                'cash', :cashier_id, :receipt_no, 0, 0, NOW())
        ");
        foreach ($sales as [$id, $storeId, $storeType, $total, $cashierId, $receiptNo]) {
            $sStmt->execute([
                'id'         => $id,
                'store_id'   => $storeId,
                'store_type' => $storeType,
                'total'      => $total,
                'cashier_id' => $cashierId,
                'receipt_no' => $receiptNo,
            ]);
        }
    }

    // -----------------------------------------------------------------------
    // Helper: simulate authenticated request via JWT
    // -----------------------------------------------------------------------

    /**
     * Build a JWT token for the given user (mirrors AuthService::generateToken logic).
     */
    private function buildJwtForUser(string $userId, string $roleId): string
    {
        $secret = $_ENV['JWT_SECRET'] ?? 'change_me_in_env';
        $now    = time();
        $payload = [
            'iss'   => $_ENV['APP_URL'] ?? 'multishop',
            'sub'   => $userId,
            'iat'   => $now,
            'exp'   => $now + 86400,
            'role'  => $roleId,
            'scope' => 'all',
        ];
        $header    = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload64 = base64_encode(json_encode($payload));
        $sig       = hash_hmac('sha256', "$header.$payload64", $secret, true);
        $sig64     = rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
        return "$header.$payload64.$sig64";
    }

    /**
     * Call SaleController::index() with a spoofed Authorization header for the given user.
     *
     * Returns the decoded JSON response body as an array.
     */
    private function callIndexAs(string $userId, string $roleId, array $queryParams = []): array
    {
        // Build JWT
        $token = $this->buildJwtForUser($userId, $roleId);

        // Spoof getallheaders() via $_SERVER
        $_SERVER['HTTP_AUTHORIZATION'] = "Bearer $token";

        // Build Request mock
        $request  = $this->buildRequest($queryParams);
        $response = new Response();

        $controller = new SaleController();
        $result     = $controller->index($request, $response);

        // Clean up spoofed header
        unset($_SERVER['HTTP_AUTHORIZATION']);

        $body = json_decode($result->getContent(), true);
        $this->assertIsArray($body, 'Response body should be valid JSON');
        return $body;
    }

    /**
     * Call SaleController::show() with a spoofed Authorization header for the given user.
     */
    private function callShowAs(string $userId, string $roleId, string $saleId): array
    {
        $token = $this->buildJwtForUser($userId, $roleId);
        $_SERVER['HTTP_AUTHORIZATION'] = "Bearer $token";

        $request    = $this->buildRequest([]);
        $response   = new Response();
        $controller = new SaleController();
        $result     = $controller->show($request, $response, ['id' => $saleId]);

        unset($_SERVER['HTTP_AUTHORIZATION']);

        $body = json_decode($result->getContent(), true);
        $this->assertIsArray($body, 'Response body should be valid JSON');
        return $body;
    }

    /**
     * Build a minimal Request object with the given query parameters.
     */
    private function buildRequest(array $queryParams): Request
    {
        // Populate $_GET so Request::getQuery() works
        $_GET = $queryParams;
        return new Request();
    }

    // -----------------------------------------------------------------------
    // Bug Condition Tests - These SHOULD FAIL on unfixed code
    // -----------------------------------------------------------------------

    /**
     * Test 1: Pharmacy Sales Personnel (Mary Njeri) receives only her own transactions.
     *
     * **Validates: Requirements 1.1, 1.2**
     *
     * On UNFIXED code: Mary receives ALL pharmacy transactions (including other-ph's),
     * so the assertion that ALL cashier_ids == 'mary-123' will FAIL.
     *
     * Counterexample: Response contains transactions with cashier_id='other-ph'
     * instead of only 'mary-123'.
     */
    public function testPharmacySalesPersonnelReceivesOnlyOwnTransactions(): void
    {
        $body = $this->callIndexAs('mary-123', 'pharmacy_sales');

        $this->assertTrue($body['success'], 'Response should be successful');
        $this->assertArrayHasKey('data', $body, 'Response should contain data');

        $transactions = $body['data'];
        $this->assertNotEmpty($transactions, 'Mary should have at least one transaction');

        // **BUG CONDITION CHECK**: ALL transactions must belong to Mary
        foreach ($transactions as $tx) {
            $this->assertEquals(
                'mary-123',
                $tx['cashier_id'],
                "COUNTEREXAMPLE FOUND: Mary Njeri (pharmacy_sales, user_id='mary-123') received " .
                "transaction ID={$tx['id']} with cashier_id='{$tx['cashier_id']}' " .
                "instead of only her own transactions. " .
                "Bug confirmed: sales personnel can see other cashiers' transactions."
            );
        }
    }

    /**
     * Test 2: Supermarket Sales Personnel (Sarah Kamau) receives only her own transactions.
     *
     * **Validates: Requirements 1.1, 1.2**
     *
     * On UNFIXED code: Sarah receives ALL supermarket transactions (including other-sm's),
     * so the assertion that ALL cashier_ids == 'sarah-456' will FAIL.
     *
     * Counterexample: Response contains transactions with cashier_id='other-sm'
     * instead of only 'sarah-456'.
     */
    public function testSupermarketSalesPersonnelReceivesOnlyOwnTransactions(): void
    {
        $body = $this->callIndexAs('sarah-456', 'supermarket_sales', ['store_type' => 'supermarket']);

        $this->assertTrue($body['success'], 'Response should be successful');
        $this->assertArrayHasKey('data', $body, 'Response should contain data');

        $transactions = $body['data'];
        $this->assertNotEmpty($transactions, 'Sarah should have at least one transaction');

        // **BUG CONDITION CHECK**: ALL transactions must belong to Sarah
        foreach ($transactions as $tx) {
            $this->assertEquals(
                'sarah-456',
                $tx['cashier_id'],
                "COUNTEREXAMPLE FOUND: Sarah Kamau (supermarket_sales, user_id='sarah-456') received " .
                "transaction ID={$tx['id']} with cashier_id='{$tx['cashier_id']}' " .
                "instead of only her own transactions. " .
                "Bug confirmed: sales personnel can see other cashiers' transactions."
            );
        }
    }

    /**
     * Test 3: Hardware Sales Personnel (David Kipchoge) receives only his own transactions.
     *
     * **Validates: Requirements 1.1, 1.2**
     *
     * On UNFIXED code: David receives ALL hardware transactions (including other-hw's),
     * so the assertion that ALL cashier_ids == 'david-789' will FAIL.
     *
     * Counterexample: Response contains transactions with cashier_id='other-hw'
     * instead of only 'david-789'.
     */
    public function testHardwareSalesPersonnelReceivesOnlyOwnTransactions(): void
    {
        $body = $this->callIndexAs('david-789', 'hardware_sales');

        $this->assertTrue($body['success'], 'Response should be successful');
        $this->assertArrayHasKey('data', $body, 'Response should contain data');

        $transactions = $body['data'];
        $this->assertNotEmpty($transactions, 'David should have at least one transaction');

        // **BUG CONDITION CHECK**: ALL transactions must belong to David
        foreach ($transactions as $tx) {
            $this->assertEquals(
                'david-789',
                $tx['cashier_id'],
                "COUNTEREXAMPLE FOUND: David Kipchoge (hardware_sales, user_id='david-789') received " .
                "transaction ID={$tx['id']} with cashier_id='{$tx['cashier_id']}' " .
                "instead of only his own transactions. " .
                "Bug confirmed: sales personnel can see other cashiers' transactions."
            );
        }
    }

    /**
     * Test 4: Sales personnel cannot access another cashier's transaction detail.
     *
     * **Validates: Requirements 1.2**
     *
     * On UNFIXED code: Mary can access a transaction belonging to other-ph,
     * so the assertion that the response is 403 Forbidden will FAIL.
     *
     * Counterexample: Response returns 200 with transaction data instead of 403 Forbidden.
     */
    public function testSalesPersonnelCannotAccessOtherCashierTransactionDetail(): void
    {
        // Insert a real transaction into the DB (using the singleton connection) so show() can find it
        $realDb = \App\Core\Database::getInstance()->getConnection();
        $otherCashierSaleId = 'test-bug-show-' . uniqid();
        $realDb->prepare("
            INSERT INTO sales (id, store_id, store_type, subtotal, discount, tax, total,
                payment_method, cashier_id, receipt_no, is_wholesale, is_refund, sale_date)
            VALUES (:id, 'store-ph-1', 'pharmacy', 150.00, 0, 0, 150.00,
                'cash', 'other-ph', 'RCP-BUGTEST001', 0, 0, NOW())
        ")->execute(['id' => $otherCashierSaleId]);

        try {
            // Mary tries to access a transaction that belongs to other-ph
            $body = $this->callShowAs('mary-123', 'pharmacy_sales', $otherCashierSaleId);

            // **BUG CONDITION CHECK**: Should be 403 Forbidden, not 200 OK
            $this->assertFalse(
                $body['success'],
                "COUNTEREXAMPLE FOUND: Mary Njeri (pharmacy_sales, user_id='mary-123') was able to access " .
                "transaction '$otherCashierSaleId' which belongs to cashier 'other-ph'. " .
                "Expected 403 Forbidden but got a successful response. " .
                "Bug confirmed: sales personnel can view other cashiers' transaction details."
            );
        } finally {
            // Clean up the test transaction
            $realDb->prepare("DELETE FROM sales WHERE id = :id")->execute(['id' => $otherCashierSaleId]);
        }
    }

    /**
     * Test 5: Property-based test - for all sales personnel roles, only own transactions returned.
     *
     * **Validates: Requirements 1.1, 1.2**
     *
     * @dataProvider salesPersonnelProvider
     *
     * Generates test cases for all three sales personnel roles and verifies that
     * each user only receives transactions where cashier_id matches their user_id.
     */
    public function testAllSalesPersonnelRolesReceiveOnlyOwnTransactions(
        string $userId,
        string $roleId,
        string $userName
    ): void {
        $body = $this->callIndexAs($userId, $roleId);

        $this->assertTrue($body['success'], "Response for $userName should be successful");
        $transactions = $body['data'];
        $this->assertNotEmpty($transactions, "$userName should have at least one transaction");

        // **BUG CONDITION CHECK**: ALL transactions must belong to this user
        $foreignTransactions = array_filter(
            $transactions,
            fn($tx) => $tx['cashier_id'] !== $userId
        );

        $this->assertEmpty(
            $foreignTransactions,
            "COUNTEREXAMPLE FOUND: $userName ($roleId, user_id='$userId') received " .
            count($foreignTransactions) . " transaction(s) belonging to other cashiers. " .
            "Foreign cashier_ids: " . implode(', ', array_unique(array_column($foreignTransactions, 'cashier_id'))) . ". " .
            "Bug confirmed: sales personnel can see other cashiers' transactions."
        );
    }

    /**
     * Data provider for all sales personnel roles.
     */
    public static function salesPersonnelProvider(): array
    {
        return [
            'pharmacy_sales'    => ['mary-123',  'pharmacy_sales',    'Mary Njeri'],
            'supermarket_sales' => ['sarah-456', 'supermarket_sales', 'Sarah Kamau'],
            'hardware_sales'    => ['david-789', 'hardware_sales',    'David Kipchoge'],
        ];
    }
}
