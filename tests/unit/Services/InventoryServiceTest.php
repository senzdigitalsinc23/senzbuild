<?php

namespace Tests\Unit\Services;

use App\Services\InventoryService;
use PHPUnit\Framework\TestCase;

class InventoryServiceTest extends TestCase
{
    private InventoryService $service;

    protected function setUp(): void
    {
        $this->service = new InventoryService();
    }

    public function test_calculates_stock_valuation(): void
    {
        $items = [
            ['product_id' => 1, 'quantity' => 10, 'cost_price' => 5.00],
            ['product_id' => 2, 'quantity' => 5,  'cost_price' => 12.50],
            ['product_id' => 3, 'quantity' => 20, 'cost_price' => 3.00],
        ];

        $expected = 10 * 5.00 + 5 * 12.50 + 20 * 3.00;
        $result = $this->service->calculateValuation($items);

        $this->assertSame($expected, $result);
    }

    public function test_identifies_low_stock_items(): void
    {
        $items = [
            ['product_id' => 1, 'quantity' => 3,  'min_stock' => 5],
            ['product_id' => 2, 'quantity' => 10, 'min_stock' => 5],
            ['product_id' => 3, 'quantity' => 0,  'min_stock' => 10],
            ['product_id' => 4, 'quantity' => 7,  'min_stock' => null],
        ];

        $lowStock = $this->service->findLowStockItems($items);

        $this->assertCount(2, $lowStock);
        $this->assertSame(1, $lowStock[0]['product_id']);
        $this->assertSame(3, $lowStock[1]['product_id']);
    }

    public function test_empty_items_returns_no_low_stock(): void
    {
        $result = $this->service->findLowStockItems([]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
