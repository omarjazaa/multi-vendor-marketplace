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
}
