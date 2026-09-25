<?php

namespace App\Repositories\Contracts;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProductRepositoryInterface
{
    /** @return Collection<int, Product> */
    public function forVendor(int $userId): iterable;

    /**
     * Paginate the products that are publicly visible in the catalog.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginateVisible(array $filters, int $perPage, string $sort): LengthAwarePaginator;

    /** Return a publicly visible product, or abort with a 404. */
    public function findVisible(int $id): Product;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Product;

    /** @param array<string, mixed> $attributes */
    public function update(Product $product, array $attributes): Product;

    public function delete(Product $product): void;
}
