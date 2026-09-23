<?php

namespace Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;
use App\Controllers\Api\v1\ConfigController;
use App\Core\Request;
use App\Core\Response;

/**
 * Unit tests for ConfigController::index()
 *
 * Verifies that the endpoint returns correct defaults when env vars are missing
 * and correct values when they are set (Task 1.3).
 */
class ConfigControllerTest extends TestCase
{
    private ConfigController $controller;
    private Request $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new ConfigController();
        // Request constructor reads from superglobals; we can pass a mock or
        // use createMock to avoid touching $_SERVER in unit tests.
        $this->request = $this->createMock(Request::class);
    }

    protected function tearDown(): void
    {
        // Clean up env vars set during tests
        foreach (['BUSINESS_LEVEL', 'BUSINESS_NAME', 'INCLUDES_PHARMACY'] as $key) {
            unset($_ENV[$key]);
        }
        parent::tearDown();
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    private function callIndex(): array
    {
        $response = new Response();
        $result = $this->controller->index($this->request, $response);
        return json_decode($result->getContent(), true);
    }

    // ─── Defaults (env vars absent) ──────────────────────────────────────────

    public function testDefaultsWhenNoEnvVarsSet(): void
    {
        unset($_ENV['BUSINESS_LEVEL'], $_ENV['BUSINESS_NAME'], $_ENV['INCLUDES_PHARMACY']);

        $data = $this->callIndex();

        $this->assertTrue($data['success']);
        $this->assertSame('starter',           $data['data']['business_level']);
        $this->assertSame('API Project', $data['data']['business_name']);
        $this->assertTrue($data['data']['includes_pharmacy']);
    }

    // ─── BUSINESS_LEVEL ──────────────────────────────────────────────────────

    public function testValidLevelStarter(): void
    {
        $_ENV['BUSINESS_LEVEL'] = 'starter';
        $data = $this->callIndex();
        $this->assertSame('starter', $data['data']['business_level']);
    }

    public function testValidLevelMedium(): void
    {
        $_ENV['BUSINESS_LEVEL'] = 'medium';
        $data = $this->callIndex();
        $this->assertSame('medium', $data['data']['business_level']);
    }

    public function testValidLevelAdvanced(): void
    {
        $_ENV['BUSINESS_LEVEL'] = 'advanced';
        $data = $this->callIndex();
        $this->assertSame('advanced', $data['data']['business_level']);
    }

    public function testLevelIsCaseInsensitive(): void
    {
        $_ENV['BUSINESS_LEVEL'] = 'MEDIUM';
        $data = $this->callIndex();
        $this->assertSame('medium', $data['data']['business_level']);
    }

    public function testInvalidLevelDefaultsToStarter(): void
    {
        $_ENV['BUSINESS_LEVEL'] = 'enterprise';
        $data = $this->callIndex();
        $this->assertSame('starter', $data['data']['business_level']);
    }

    public function testEmptyLevelDefaultsToStarter(): void
    {
        $_ENV['BUSINESS_LEVEL'] = '';
        $data = $this->callIndex();
        $this->assertSame('starter', $data['data']['business_level']);
    }

    public function testWhitespaceLevelDefaultsToStarter(): void
    {
        $_ENV['BUSINESS_LEVEL'] = '   ';
        $data = $this->callIndex();
        $this->assertSame('starter', $data['data']['business_level']);
    }

    // ─── BUSINESS_NAME ───────────────────────────────────────────────────────

    public function testCustomBusinessName(): void
    {
        $_ENV['BUSINESS_NAME'] = 'Acme Corp';
        $data = $this->callIndex();
        $this->assertSame('Acme Corp', $data['data']['business_name']);
    }

    public function testEmptyBusinessNameDefaultsToAPIProject(): void
    {
        $_ENV['BUSINESS_NAME'] = '';
        $data = $this->callIndex();
        $this->assertSame('API Project', $data['data']['business_name']);
    }

    public function testWhitespaceBusinessNameDefaultsToAPIProject(): void
    {
        $_ENV['BUSINESS_NAME'] = '   ';
        $data = $this->callIndex();
        $this->assertSame('API Project', $data['data']['business_name']);
    }

    // ─── INCLUDES_PHARMACY ───────────────────────────────────────────────────

    public function testIncludesPharmacyTrueString(): void
    {
        $_ENV['INCLUDES_PHARMACY'] = 'true';
        $data = $this->callIndex();
        $this->assertTrue($data['data']['includes_pharmacy']);
    }

    public function testIncludesPharmacyOneString(): void
    {
        $_ENV['INCLUDES_PHARMACY'] = '1';
        $data = $this->callIndex();
        $this->assertTrue($data['data']['includes_pharmacy']);
    }

    public function testIncludesPharmacyYesString(): void
    {
        $_ENV['INCLUDES_PHARMACY'] = 'yes';
        $data = $this->callIndex();
        $this->assertTrue($data['data']['includes_pharmacy']);
    }

    public function testIncludesPharmacyFalseString(): void
    {
        $_ENV['INCLUDES_PHARMACY'] = 'false';
        $data = $this->callIndex();
        $this->assertFalse($data['data']['includes_pharmacy']);
    }

    public function testIncludesPharmacyZeroString(): void
    {
        $_ENV['INCLUDES_PHARMACY'] = '0';
        $data = $this->callIndex();
        $this->assertFalse($data['data']['includes_pharmacy']);
    }

    public function testIncludesPharmacyNoString(): void
    {
        $_ENV['INCLUDES_PHARMACY'] = 'no';
        $data = $this->callIndex();
        $this->assertFalse($data['data']['includes_pharmacy']);
    }

    public function testIncludesPharmacyMissingDefaultsToTrue(): void
    {
        unset($_ENV['INCLUDES_PHARMACY']);
        $data = $this->callIndex();
        $this->assertTrue($data['data']['includes_pharmacy']);
    }

    public function testIncludesPharmacyEmptyDefaultsToTrue(): void
    {
        $_ENV['INCLUDES_PHARMACY'] = '';
        $data = $this->callIndex();
        $this->assertTrue($data['data']['includes_pharmacy']);
    }

    public function testIncludesPharmacyCaseInsensitive(): void
    {
        $_ENV['INCLUDES_PHARMACY'] = 'TRUE';
        $data = $this->callIndex();
        $this->assertTrue($data['data']['includes_pharmacy']);
    }

    // ─── Response structure ───────────────────────────────────────────────────

    public function testResponseHasSuccessTrue(): void
    {
        $data = $this->callIndex();
        $this->assertTrue($data['success']);
    }

    public function testResponseDataHasAllKeys(): void
    {
        $data = $this->callIndex();
        $this->assertArrayHasKey('business_level',    $data['data']);
        $this->assertArrayHasKey('business_name',     $data['data']);
        $this->assertArrayHasKey('includes_pharmacy', $data['data']);
    }

    public function testResponseIsValidJson(): void
    {
        $response = new Response();
        $result = $this->controller->index($this->request, $response);
        $decoded = json_decode($result->getContent(), true);
        $this->assertNotNull($decoded, 'Response content should be valid JSON');
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
    }
}
