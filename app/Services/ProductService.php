<?php

namespace App\Services;

use App\Models\Product;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly InventoryRepositoryInterface $inventories,
    ) {}

    /** Return products belonging to the vendor's stores. */
    public function forVendor(int $userId): iterable
    {
        return $this->products->forVendor($userId);
    }

    /**
     * Create a product and open its empty stock record.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Product
    {
        return DB::transaction(function () use ($attributes): Product {
            $product = $this->products->create($attributes);

            $this->inventories->firstOrCreateForProduct($product);

            return $product->load('inventory');
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(Product $product, array $attributes): Product
    {
        return $this->products->update($product, $attributes);
    }

    public function delete(Product $product): void
    {
        $this->products->delete($product);
    }
}
