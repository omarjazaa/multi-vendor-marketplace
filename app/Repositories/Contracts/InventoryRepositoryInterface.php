<?php

namespace App\Repositories\Contracts;

use App\Models\Inventory;
use App\Models\Product;

interface InventoryRepositoryInterface
{
    /**
     * Return the stock record of a product, initialising an empty one when missing.
     */
    public function firstOrCreateForProduct(Product $product): Inventory;

    /** @param array<string, mixed> $attributes */
    public function update(Inventory $inventory, array $attributes): Inventory;

    /**
     * Atomically deduct stock for an order line.
     *
     * Runs as a single guarded UPDATE (quantity >= requested) so concurrent
     * checkouts can never drive the level negative.
     */
    public function decrementQuantity(Product $product, int $quantity): bool;
}
