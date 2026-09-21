<?php

namespace App\Services;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;

class ProductService
{
    public function __construct(private readonly ProductRepositoryInterface $products) {}

    /** Return products belonging to the vendor's stores. */
    public function forVendor(int $userId): iterable
    {
        return $this->products->forVendor($userId);
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Product
    {
        return $this->products->create($attributes);
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
