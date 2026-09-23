<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Controllers\Api\v1\SaleController;
use App\Core\Request;
use App\Core\Response;
use PDO;

/**
 * Preservation Property Tests - Manager and Administrator Full Visibility
 *
 * **Validates: Requirements 3.1, 3.2, 3.3, 3.4**
 *
 * **IMPORTANT**: Follow observation-first methodology.
 * These tests observe behavior on UNFIXED code for non-buggy inputs (manager and
 * administrator roles) and capture that behavior as properties to preserve.
 *
 * Property 2: Preservation - Manager and Administrator Full Visibility
 *   For any API request to GET /api/v1/sales where the authenticated user does NOT have
 *   a sales personnel role (e.g., general_manager, pharmacy_manager, admin), the fixed
 *   system SHALL produce exactly the same result as the original system, preserving full
 *   visibility across all transactions within their authorized scope.
 *
 * **EXPECTED OUTCOME**: Tests PASS (confirms baseline behavior to preserve)
 *
 * These tests run against the real database to observe actual behavior.
 * They do NOT use temporary tables because we want to observe real data.
 */
class SaleControllerPreservationTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Setup / teardown
    // -----------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        // Clean up any test transactions inserted during tests
        $realDb = \App\Core\Database::getInstance()->getConnection();
        $realDb->exec("DELETE FROM sales WHERE receipt_no LIKE 'RCP-PRESERVE-%'");
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Helper: simulate authenticated request via JWT
    // -----------------------------------------------------------------------

    /**
     * Build a JWT token for the given user (mirrors AuthService::generateToken logic).
     */
    private function buildJwtForUser(string $userId, string $roleId, string $storeScope = 'all'): string
    {
        $secret = $_ENV['JWT_SECRET'] ?? 'change_me_in_env';
        $now    = time();
        $payload = [
            'iss'   => $_ENV['APP_URL'] ?? 'multishop',
            'sub'   => $userId,
            'iat'   => $now,
            'exp'   => $now + 86400,
            'role'  => $roleId,
            'scope' => $storeScope,
        ];
        $header    = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload64 = base64_encode(json_encode($payload));
        $sig       = hash_hmac('sha256', "$header.$payload64", $secret, true);
        $sig64     = rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
        return "$header.$payload64.$sig64";
    }

    /**
     * Call SaleController::index() with a spoofed Authorization header for the given user.
     * Returns the decoded JSON response body as an array.
     */
    private function callIndexAs(string $userId, string $roleId, array $queryParams = []): array
    {
        $token = $this->buildJwtForUser($userId, $roleId);
        $_SERVER['HTTP_AUTHORIZATION'] = "Bearer $token";
        $_GET = $queryParams;

        $request    = new Request();
        $response   = new Response();
        $controller = new SaleController();
        $result     = $controller->index($request, $response);

        unset($_SERVER['HTTP_AUTHORIZATION']);
        $_GET = [];

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
        $_GET = [];

        $request    = new Request();
        $response   = new Response();
        $controller = new SaleController();
        $result     = $controller->show($request, $response, ['id' => $saleId]);

        unset($_SERVER['HTTP_AUTHORIZATION']);

        $body = json_decode($result->getContent(), true);
        $this->assertIsArray($body, 'Response body should be valid JSON');
        return $body;
    }

    /**
     * Insert a test transaction into the real DB and return its ID.
     * Uses a unique receipt_no prefix so tearDown can clean up.
     */
    private function insertTestSale(
        string $cashierId,
        string $storeType = 'pharmacy',
        string $storeId = 'store-ph-1',
        float $total = 100.00
    ): string {
        $realDb = \App\Core\Database::getInstance()->getConnection();
        $id = 'test-preserve-' . uniqid();
        $receiptNo = 'RCP-PRESERVE-' . strtoupper(substr($id, -8));
        $realDb->prepare("
            INSERT INTO sales (id, store_id, store_type, subtotal, discount, tax, total,
                payment_method, cashier_id, receipt_no, is_wholesale, is_refund, sale_date)
            VALUES (:id, :store_id, :store_type, :total, 0, 0, :total,
                'cash', :cashier_id, :receipt_no, 0, 0, NOW())
        ")->execute([
            'id'         => $id,
            'store_id'   => $storeId,
            'store_type' => $storeType,
            'total'      => $total,
            'cashier_id' => $cashierId,
            'receipt_no' => $receiptNo,
        ]);
        return $id;
    }

    // -----------------------------------------------------------------------
    // Preservation Tests - These SHOULD PASS on both unfixed and fixed code
    // -----------------------------------------------------------------------

    /**
     * Test 1: General Manager has full visibility to all transactions.
     *
     * **Validates: Requirements 3.1**
     *
     * Observes that general_manager can view all transactions on unfixed code,
     * then verifies this continues after fix.
     *
     * @dataProvider managerRolesProvider
     */
    public function testManagerRolesReceiveAllTransactions(
        string $userId,
        string $roleId,
        string $roleName
    ): void {
        // Insert test transactions from two different cashiers
        $cashier1Id = 'preserve-cashier-1-' . uniqid();
        $cashier2Id = 'preserve-cashier-2-' . uniqid();
        $sale1Id = $this->insertTestSale($cashier1Id, 'pharmacy', 'store-ph-1', 100.00);
        $sale2Id = $this->insertTestSale($cashier2Id, 'pharmacy', 'store-ph-1', 200.00);

        try {
            $body = $this->callIndexAs($userId, $roleId);

            $this->assertTrue($body['success'], "$roleName response should be successful");
            $this->assertArrayHasKey('data', $body, "$roleName response should contain data");

            $transactions = $body['data'];
            $transactionIds = array_column($transactions, 'id');

            // **PRESERVATION CHECK**: Manager should see BOTH cashiers' transactions
            $this->assertContains(
                $sale1Id,
                $transactionIds,
                "$roleName should be able to see transaction from cashier 1 ($cashier1Id)"
            );
            $this->assertContains(
                $sale2Id,
                $transactionIds,
                "$roleName should be able to see transaction from cashier 2 ($cashier2Id)"
            );
        } finally {
            $realDb = \App\Core\Database::getInstance()->getConnection();
            $realDb->prepare("DELETE FROM sales WHERE id IN (:id1, :id2)")->execute([
                'id1' => $sale1Id,
                'id2' => $sale2Id,
            ]);
        }
    }

    /**
     * Data provider for all manager and administrator roles.
     */
    public static function managerRolesProvider(): array
    {
        return [
            'general_manager'       => ['mgr-general-001',  'general_manager',       'General Manager'],
            'pharmacy_manager'      => ['mgr-pharmacy-001', 'pharmacy_manager',      'Pharmacy Manager'],
            'supermarket_manager'   => ['mgr-super-001',    'supermarket_manager',   'Supermarket Manager'],
            'hardware_manager'      => ['mgr-hardware-001', 'hardware_manager',      'Hardware Manager'],
            'general_stock_manager' => ['mgr-stock-001',    'general_stock_manager', 'General Stock Manager'],
            'admin'                 => ['admin-001',         'admin',                 'Administrator'],
        ];
    }

    /**
     * Test 2: Existing filters (store_type, date_from, date_to) continue to work for managers.
     *
     * **Validates: Requirements 3.3**
     *
     * Verifies that filter parameters work correctly for manager roles after the fix.
     */
    public function testManagerFiltersWorkCorrectly(): void
    {
        $cashierId = 'preserve-filter-cashier-' . uniqid();
        $pharmacySaleId    = $this->insertTestSale($cashierId, 'pharmacy',    'store-ph-1', 100.00);
        $supermarketSaleId = $this->insertTestSale($cashierId, 'supermarket', 'store-sm-1', 200.00);
        $hardwareSaleId    = $this->insertTestSale($cashierId, 'hardware',    'store-hw-1', 300.00);

        try {
            // Filter by store_type=pharmacy - should only return pharmacy transactions
            $body = $this->callIndexAs('mgr-general-001', 'general_manager', ['store_type' => 'pharmacy']);
            $this->assertTrue($body['success'], 'Filtered response should be successful');

            $transactionIds = array_column($body['data'], 'id');
            $this->assertContains($pharmacySaleId, $transactionIds, 'Pharmacy filter should include pharmacy sale');
            $this->assertNotContains($supermarketSaleId, $transactionIds, 'Pharmacy filter should exclude supermarket sale');
            $this->assertNotContains($hardwareSaleId, $transactionIds, 'Pharmacy filter should exclude hardware sale');

            // Filter by store_type=supermarket
            $body = $this->callIndexAs('mgr-general-001', 'general_manager', ['store_type' => 'supermarket']);
            $transactionIds = array_column($body['data'], 'id');
            $this->assertContains($supermarketSaleId, $transactionIds, 'Supermarket filter should include supermarket sale');
            $this->assertNotContains($pharmacySaleId, $transactionIds, 'Supermarket filter should exclude pharmacy sale');

            // Filter by date_from (today) - should include today's transactions
            $today = date('Y-m-d');
            $body = $this->callIndexAs('mgr-general-001', 'general_manager', ['date_from' => $today]);
            $transactionIds = array_column($body['data'], 'id');
            $this->assertContains($pharmacySaleId, $transactionIds, 'Date filter should include today\'s pharmacy sale');
            $this->assertContains($supermarketSaleId, $transactionIds, 'Date filter should include today\'s supermarket sale');

            // Filter by date_to (yesterday) - should NOT include today's transactions
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $body = $this->callIndexAs('mgr-general-001', 'general_manager', ['date_to' => $yesterday]);
            $transactionIds = array_column($body['data'], 'id');
            $this->assertNotContains($pharmacySaleId, $transactionIds, 'Yesterday date_to filter should exclude today\'s pharmacy sale');
        } finally {
            $realDb = \App\Core\Database::getInstance()->getConnection();
            $realDb->prepare("DELETE FROM sales WHERE id IN (:id1, :id2, :id3)")->execute([
                'id1' => $pharmacySaleId,
                'id2' => $supermarketSaleId,
                'id3' => $hardwareSaleId,
            ]);
        }
    }

    /**
     * Test 3: Administrator can access any transaction detail.
     *
     * **Validates: Requirements 3.2**
     *
     * Verifies that admin role can view any transaction detail without restrictions.
     */
    public function testAdministratorCanAccessAnyTransactionDetail(): void
    {
        $cashierId = 'preserve-admin-cashier-' . uniqid();
        $saleId = $this->insertTestSale($cashierId, 'pharmacy', 'store-ph-1', 150.00);

        try {
            $body = $this->callShowAs('admin-001', 'admin', $saleId);

            // **PRESERVATION CHECK**: Admin should be able to access any transaction
            $this->assertTrue(
                $body['success'],
                "Administrator should be able to access any transaction detail. " .
                "Got: " . json_encode($body)
            );
            $this->assertArrayHasKey('data', $body, 'Response should contain transaction data');
            $this->assertEquals($saleId, $body['data']['id'], 'Response should contain the correct transaction');
        } finally {
            $realDb = \App\Core\Database::getInstance()->getConnection();
            $realDb->prepare("DELETE FROM sales WHERE id = :id")->execute(['id' => $saleId]);
        }
    }

    /**
     * Test 4: Manager can access any transaction detail.
     *
     * **Validates: Requirements 3.2**
     *
     * Verifies that manager roles can view any transaction detail without restrictions.
     *
     * @dataProvider managerRolesProvider
     */
    public function testManagerCanAccessAnyTransactionDetail(
        string $userId,
        string $roleId,
        string $roleName
    ): void {
        $cashierId = 'preserve-mgr-cashier-' . uniqid();
        $saleId = $this->insertTestSale($cashierId, 'pharmacy', 'store-ph-1', 150.00);

        try {
            $body = $this->callShowAs($userId, $roleId, $saleId);

            // **PRESERVATION CHECK**: Manager should be able to access any transaction
            $this->assertTrue(
                $body['success'],
                "$roleName should be able to access any transaction detail. " .
                "Got: " . json_encode($body)
            );
            $this->assertArrayHasKey('data', $body, 'Response should contain transaction data');
            $this->assertEquals($saleId, $body['data']['id'], 'Response should contain the correct transaction');
        } finally {
            $realDb = \App\Core\Database::getInstance()->getConnection();
            $realDb->prepare("DELETE FROM sales WHERE id = :id")->execute(['id' => $saleId]);
        }
    }

    /**
     * Test 5: Sales personnel can view complete details of their own transactions.
     *
     * **Validates: Requirements 3.2**
     *
     * Verifies that sales personnel can still access their own transaction details.
     *
     * @dataProvider salesPersonnelOwnTransactionProvider
     */
    public function testSalesPersonnelCanViewOwnTransactionDetails(
        string $userId,
        string $roleId,
        string $userName
    ): void {
        // Insert a transaction belonging to this sales person
        $saleId = $this->insertTestSale($userId, 'pharmacy', 'store-ph-1', 100.00);

        try {
            $body = $this->callShowAs($userId, $roleId, $saleId);

            // **PRESERVATION CHECK**: Sales personnel should be able to access their own transactions
            $this->assertTrue(
                $body['success'],
                "$userName should be able to access their own transaction detail. " .
                "Got: " . json_encode($body)
            );
            $this->assertArrayHasKey('data', $body, 'Response should contain transaction data');
            $this->assertEquals($saleId, $body['data']['id'], 'Response should contain the correct transaction');
            $this->assertEquals($userId, $body['data']['cashier_id'], 'Transaction should belong to this sales person');
        } finally {
            $realDb = \App\Core\Database::getInstance()->getConnection();
            $realDb->prepare("DELETE FROM sales WHERE id = :id")->execute(['id' => $saleId]);
        }
    }

    /**
     * Data provider for sales personnel own transaction tests.
     */
    public static function salesPersonnelOwnTransactionProvider(): array
    {
        return [
            'pharmacy_sales'    => ['mary-123',  'pharmacy_sales',    'Mary Njeri'],
            'supermarket_sales' => ['sarah-456', 'supermarket_sales', 'Sarah Kamau'],
            'hardware_sales'    => ['david-789', 'hardware_sales',    'David Kipchoge'],
        ];
    }

    /**
     * Test 6: Property-based test - manager roles always see all transactions regardless of filters.
     *
     * **Validates: Requirements 3.1, 3.3**
     *
     * @dataProvider managerFilterCombinationsProvider
     *
     * Generates many filter combinations and verifies that manager roles always
     * receive all transactions within the filter scope (not restricted by cashier_id).
     */
    public function testManagerAlwaysSeesAllTransactionsWithAnyFilter(
        string $roleId,
        array $filters,
        string $description
    ): void {
        // Insert two transactions from different cashiers matching the filter criteria
        $cashier1 = 'preserve-prop-c1-' . uniqid();
        $cashier2 = 'preserve-prop-c2-' . uniqid();
        $storeType = $filters['store_type'] ?? 'pharmacy';
        $sale1Id = $this->insertTestSale($cashier1, $storeType, 'store-ph-1', 100.00);
        $sale2Id = $this->insertTestSale($cashier2, $storeType, 'store-ph-1', 200.00);

        try {
            $body = $this->callIndexAs('mgr-general-001', $roleId, $filters);

            $this->assertTrue($body['success'], "Manager ($roleId) response should be successful for: $description");
            $transactionIds = array_column($body['data'], 'id');

            // **PRESERVATION CHECK**: Manager should see BOTH cashiers' transactions
            $this->assertContains(
                $sale1Id,
                $transactionIds,
                "Manager ($roleId) should see cashier 1's transaction for: $description"
            );
            $this->assertContains(
                $sale2Id,
                $transactionIds,
                "Manager ($roleId) should see cashier 2's transaction for: $description"
            );
        } finally {
            $realDb = \App\Core\Database::getInstance()->getConnection();
            $realDb->prepare("DELETE FROM sales WHERE id IN (:id1, :id2)")->execute([
                'id1' => $sale1Id,
                'id2' => $sale2Id,
            ]);
        }
    }

    /**
     * Data provider for manager filter combinations.
     * Generates various filter combinations to test preservation across many scenarios.
     */
    public static function managerFilterCombinationsProvider(): array
    {
        $today = date('Y-m-d');
        $cases = [];

        $roles = ['general_manager', 'pharmacy_manager', 'admin'];
        $filterSets = [
            'no_filters'           => [],
            'pharmacy_filter'      => ['store_type' => 'pharmacy'],
            'date_from_today'      => ['date_from' => $today],
            'date_to_today'        => ['date_to' => $today],
            'pharmacy_date_today'  => ['store_type' => 'pharmacy', 'date_from' => $today],
        ];

        foreach ($roles as $role) {
            foreach ($filterSets as $filterName => $filters) {
                $key = "{$role}_{$filterName}";
                $cases[$key] = [$role, $filters, "$role with $filterName"];
            }
        }

        return $cases;
    }

    /**
     * Test 7: myDailySummary endpoint continues to work for all user roles.
     *
     * **Validates: Requirements 3.4**
     *
     * Verifies that the myDailySummary endpoint still works correctly after the fix.
     * This endpoint already correctly filters by cashier_id and should remain unchanged.
     */
    public function testMyDailySummaryEndpointContinuesToWork(): void
    {
        $cashierId = 'preserve-daily-cashier-' . uniqid();
        $saleId = $this->insertTestSale($cashierId, 'pharmacy', 'store-ph-1', 100.00);

        try {
            // Build JWT for this cashier
            $token = $this->buildJwtForUser($cashierId, 'pharmacy_sales');
            $_SERVER['HTTP_AUTHORIZATION'] = "Bearer $token";
            $_GET = ['date' => date('Y-m-d')];

            $request    = new Request();
            $response   = new Response();
            $controller = new SaleController();
            $result     = $controller->myDailySummary($request, $response);

            unset($_SERVER['HTTP_AUTHORIZATION']);
            $_GET = [];

            $body = json_decode($result->getContent(), true);
            $this->assertIsArray($body, 'myDailySummary response should be valid JSON');
            $this->assertTrue($body['success'], 'myDailySummary should return success');
            $this->assertArrayHasKey('data', $body, 'myDailySummary should return data');

            // **PRESERVATION CHECK**: The summary should include today's transaction
            $data = $body['data'];
            $this->assertArrayHasKey('transactions', $data, 'Summary should include transaction count');
            $this->assertArrayHasKey('revenue', $data, 'Summary should include revenue');
            $this->assertGreaterThanOrEqual(1, (int)$data['transactions'], 'Summary should count at least 1 transaction');
            $this->assertGreaterThanOrEqual(100.00, (float)$data['revenue'], 'Summary revenue should be at least 100.00');
        } finally {
            $realDb = \App\Core\Database::getInstance()->getConnection();
            $realDb->prepare("DELETE FROM sales WHERE id = :id")->execute(['id' => $saleId]);
        }
    }

    /**
     * Test 8: cashier_id filter still works for manager roles (explicit filter).
     *
     * **Validates: Requirements 3.3**
     *
     * Verifies that managers can still filter by a specific cashier_id.
     */
    public function testManagerCanFilterByCashierId(): void
    {
        $cashier1 = 'preserve-cf-c1-' . uniqid();
        $cashier2 = 'preserve-cf-c2-' . uniqid();
        $sale1Id = $this->insertTestSale($cashier1, 'pharmacy', 'store-ph-1', 100.00);
        $sale2Id = $this->insertTestSale($cashier2, 'pharmacy', 'store-ph-1', 200.00);

        try {
            // Manager filters by cashier1's ID
            $body = $this->callIndexAs('mgr-general-001', 'general_manager', ['cashier_id' => $cashier1]);

            $this->assertTrue($body['success'], 'Filtered response should be successful');
            $transactionIds = array_column($body['data'], 'id');

            // **PRESERVATION CHECK**: Filter by cashier_id should work for managers
            $this->assertContains($sale1Id, $transactionIds, 'Manager cashier_id filter should include cashier1 sale');
            $this->assertNotContains($sale2Id, $transactionIds, 'Manager cashier_id filter should exclude cashier2 sale');
        } finally {
            $realDb = \App\Core\Database::getInstance()->getConnection();
            $realDb->prepare("DELETE FROM sales WHERE id IN (:id1, :id2)")->execute([
                'id1' => $sale1Id,
                'id2' => $sale2Id,
            ]);
        }
    }
}
