<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Inventory calculation service
 */
class InventoryService
{
    /**
     * Calculate total stock valuation
     *
     * @param array<int, array{product_id: int, quantity: float, cost_price: float}> $items
     * @return float
     */
    public function calculateValuation(array $items): float
    {
        $total = 0.0;
        foreach ($items as $item) {
            $quantity = (float)($item['quantity'] ?? 0);
            $costPrice = (float)($item['cost_price'] ?? 0);
            $total += $quantity * $costPrice;
        }
        return $total;
    }

    /**
     * Find items below minimum stock level
     *
     * @param array<int, array{product_id: int, quantity: float, min_stock: float|null}> $items
     * @return array<int, array{product_id: int, quantity: float, min_stock: float|null}>
     */
    public function findLowStockItems(array $items): array
    {
        $lowStock = [];
        foreach ($items as $item) {
            $quantity = (float)($item['quantity'] ?? 0);
            $minStock = $item['min_stock'] ?? null;

            if ($minStock !== null && $quantity < (float)$minStock) {
                $lowStock[] = $item;
            }
        }
        return $lowStock;
    }
}
