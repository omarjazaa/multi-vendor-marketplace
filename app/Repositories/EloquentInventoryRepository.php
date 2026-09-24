<?php

namespace App\Repositories;

use App\Models\Inventory;
use App\Models\Product;
use App\Repositories\Contracts\InventoryRepositoryInterface;

class EloquentInventoryRepository implements InventoryRepositoryInterface
{
    /**
     * Stock records are created on demand so that every product reports a level.
     */
    public function firstOrCreateForProduct(Product $product): Inventory
    {
        return Inventory::firstOrCreate(
            ['product_id' => $product->id],
            ['quantity' => 0, 'low_stock_threshold' => $this->defaultLowStockThreshold()],
        );
    }

    /** @param array<string, mixed> $attributes */
    public function update(Inventory $inventory, array $attributes): Inventory
    {
        $inventory->update($attributes);

        return $inventory->refresh();
    }

    /** Read the platform default alert threshold used for new stock records. */
    private function defaultLowStockThreshold(): int
    {
        return (int) config('marketplace.inventory.default_low_stock_threshold');
    }
}
