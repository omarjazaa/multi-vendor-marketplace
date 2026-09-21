<?php

namespace App\Repositories\Contracts;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

interface ProductRepositoryInterface
{
    /** @return Collection<int, Product> */
    public function forVendor(int $userId): iterable;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Product;

    /** @param array<string, mixed> $attributes */
    public function update(Product $product, array $attributes): Product;

    public function delete(Product $product): void;
}
