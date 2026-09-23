<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\StoreCredit;
use Jobs\ExpireStoreCreditJob;
use App\Core\Database;
use PDO;

/**
 * Unit tests for Store Credit Expiration functionality
 * 
 * Tests:
 * - Expired credit usage prevention
 * - Expiration job execution
 * - Audit logging for expirations
 */
class StoreCreditExpirationTest extends TestCase
{
    protected ?PDO $db;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Test that expired credits cannot be used
     */
    public function testExpiredCreditCannotBeUsed(): void
    {
        // Create a credit that expired yesterday
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        $result = StoreCredit::create([
            'customer_id' => 'test-customer-' . uniqid(),
            'amount' => 100.00,
            'source_type' => 'manual',
            'expiry_date' => $yesterday,
            'notes' => 'Test expired credit',
        ]);
        
        $this->assertTrue($result['success'], 'Credit creation should succeed');
        $creditId = $result['data']['id'];
        
        // Try to use the expired credit
        $useResult = StoreCredit::use($creditId, 50.00);
        
        $this->assertFalse($useResult['success'], 'Using expired credit should fail');
        $this->assertStringContainsString('expired', strtolower($useResult['message']), 'Error message should mention expiration');
        
        // Cleanup
        $this->db->exec("DELETE FROM store_credit_transactions WHERE credit_id = '{$creditId}'");
        $this->db->exec("DELETE FROM store_credits WHERE id = '{$creditId}'");
    }
    
    /**
     * Test that non-expired credits can still be used
     */
    public function testNonExpiredCreditCanBeUsed(): void
    {
        // Create a credit that expires in 30 days
        $futureDate = date('Y-m-d', strtotime('+30 days'));
        
        $result = StoreCredit::create([
            'customer_id' => 'test-customer-' . uniqid(),
            'amount' => 100.00,
            'source_type' => 'manual',
            'expiry_date' => $futureDate,
            'notes' => 'Test future expiry credit',
        ]);
        
        $this->assertTrue($result['success'], 'Credit creation should succeed');
        $creditId = $result['data']['id'];
        
        // Try to use the credit
        $useResult = StoreCredit::use($creditId, 50.00);
        
        $this->assertTrue($useResult['success'], 'Using non-expired credit should succeed');
        $this->assertEquals(50.00, $useResult['data']['balance'], 'Balance should be reduced');
        
        // Cleanup
        $this->db->exec("DELETE FROM store_credit_transactions WHERE credit_id = '{$creditId}'");
        $this->db->exec("DELETE FROM store_credits WHERE id = '{$creditId}'");
    }
    
    /**
     * Test that credits without expiry date can be used
     */
    public function testCreditWithoutExpiryCanBeUsed(): void
    {
        $result = StoreCredit::create([
            'customer_id' => 'test-customer-' . uniqid(),
            'amount' => 100.00,
            'source_type' => 'manual',
            'expiry_date' => null, // No expiry
            'notes' => 'Test no expiry credit',
        ]);
        
        $this->assertTrue($result['success'], 'Credit creation should succeed');
        $creditId = $result['data']['id'];
        
        // Try to use the credit
        $useResult = StoreCredit::use($creditId, 50.00);
        
        $this->assertTrue($useResult['success'], 'Using credit without expiry should succeed');
        $this->assertEquals(50.00, $useResult['data']['balance'], 'Balance should be reduced');
        
        // Cleanup
        $this->db->exec("DELETE FROM store_credit_transactions WHERE credit_id = '{$creditId}'");
        $this->db->exec("DELETE FROM store_credits WHERE id = '{$creditId}'");
    }
    
    /**
     * Test expiration job finds expired credits
     */
    public function testExpirationJobFindsExpiredCredits(): void
    {
        // Create test credits
        $expiredDate = date('Y-m-d', strtotime('-5 days'));
        $futureDate = date('Y-m-d', strtotime('+30 days'));
        
        $expired1 = StoreCredit::create([
            'customer_id' => 'test-customer-' . uniqid(),
            'amount' => 100.00,
            'source_type' => 'manual',
            'expiry_date' => $expiredDate,
            'notes' => 'Test expired 1',
        ]);
        
        $expired2 = StoreCredit::create([
            'customer_id' => 'test-customer-' . uniqid(),
            'amount' => 50.00,
            'source_type' => 'manual',
            'expiry_date' => $expiredDate,
            'notes' => 'Test expired 2',
        ]);
        
        $active = StoreCredit::create([
            'customer_id' => 'test-customer-' . uniqid(),
            'amount' => 75.00,
            'source_type' => 'manual',
            'expiry_date' => $futureDate,
            'notes' => 'Test active',
        ]);
        
        $this->assertTrue($expired1['success'] && $expired2['success'] && $active['success']);
        
        // Run expiration job
        $job = new ExpireStoreCreditJob();
        
        // Capture output to avoid risky test warnings
        ob_start();
        $result = $job->execute();
        ob_end_clean();
        
        $this->assertTrue($result['success'], 'Job should complete successfully');
        $this->assertGreaterThanOrEqual(2, $result['expired_count'], 'Should expire at least 2 credits');
        
        // Verify expired credits have status = 'expired'
        $credit1 = StoreCredit::findById($expired1['data']['id']);
        $credit2 = StoreCredit::findById($expired2['data']['id']);
        $credit3 = StoreCredit::findById($active['data']['id']);
        
        $this->assertEquals('expired', $credit1['status'], 'First credit should be expired');
        $this->assertEquals('expired', $credit2['status'], 'Second credit should be expired');
        $this->assertEquals('active', $credit3['status'], 'Active credit should remain active');
        
        // Cleanup
        foreach ([$expired1, $expired2, $active] as $c) {
            $id = $c['data']['id'];
            $this->db->exec("DELETE FROM store_credit_transactions WHERE credit_id = '{$id}'");
            $this->db->exec("DELETE FROM store_credits WHERE id = '{$id}'");
        }
    }
    
    /**
     * Test expiration job handles no expired credits gracefully
     */
    public function testExpirationJobWithNoExpiredCredits(): void
    {
        // Create only future-dated credits
        $futureDate = date('Y-m-d', strtotime('+30 days'));
        
        $credit = StoreCredit::create([
            'customer_id' => 'test-customer-' . uniqid(),
            'amount' => 100.00,
            'source_type' => 'manual',
            'expiry_date' => $futureDate,
            'notes' => 'Test future credit',
        ]);
        
        $this->assertTrue($credit['success']);
        
        // Run expiration job
        $job = new ExpireStoreCreditJob();
        
        // Capture output
        ob_start();
        $result = $job->execute();
        ob_end_clean();
        
        $this->assertTrue($result['success'], 'Job should complete successfully even with no expired credits');
        
        // Cleanup
        $id = $credit['data']['id'];
        $this->db->exec("DELETE FROM store_credit_transactions WHERE credit_id = '{$id}'");
        $this->db->exec("DELETE FROM store_credits WHERE id = '{$id}'");
    }
}
