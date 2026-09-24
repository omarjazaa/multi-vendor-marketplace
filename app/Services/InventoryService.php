<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Product;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function __construct(private readonly InventoryRepositoryInterface $inventories) {}

    /** Return the tracked stock record for a product, creating defaults when needed. */
    public function forProduct(Product $product): Inventory
    {
        return $this->inventories->firstOrCreateForProduct($product);
    }

    /**
     * Set the stock level, and optionally the alert threshold, for a product.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Product $product, array $attributes): Inventory
    {
        return DB::transaction(function () use ($product, $attributes): Inventory {
            $inventory = $this->inventories->firstOrCreateForProduct($product);

            return $this->inventories->update($inventory, array_filter(
                $attributes,
                static fn (mixed $value): bool => $value !== null,
            ));
        });
    }
}
