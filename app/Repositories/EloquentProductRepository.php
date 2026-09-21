<?php

namespace App\Repositories;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentProductRepository implements ProductRepositoryInterface
{
    /** @return Collection<int, Product> */
    public function forVendor(int $userId): iterable
    {
        return Product::whereHas('store.vendorProfile', fn ($query) => $query->where('user_id', $userId))
            ->with(['store', 'category'])
            ->latest()
            ->get();
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Product
    {
        return Product::create($attributes)->load(['store', 'category']);
    }

    /** @param array<string, mixed> $attributes */
    public function update(Product $product, array $attributes): Product
    {
        $product->update($attributes);

        return $product->fresh(['store', 'category']);
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }
}
