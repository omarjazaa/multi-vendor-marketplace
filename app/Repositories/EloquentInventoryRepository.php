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

    /**
     * Atomically deduct stock for an order line.
     *
     * The WHERE guard (quantity >= requested) and the decrement happen in one
     * UPDATE statement, so the affected-rows count tells us whether the shelf
     * held enough units: 0 rows means insufficient or untracked stock.
     */
    public function decrementQuantity(Product $product, int $quantity): bool
    {
        $affected = Inventory::query()
            ->where('product_id', $product->id)
            ->where('quantity', '>=', $quantity)
            ->decrement('quantity', $quantity);

        return (int) $affected > 0;
    }

    /** Read the platform default alert threshold used for new stock records. */
    private function defaultLowStockThreshold(): int
    {
        return (int) config('marketplace.inventory.default_low_stock_threshold');
    }
}
