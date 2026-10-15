<?php

namespace App\Repositories;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\CatalogCacheService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

/**
 * Decorator for ProductRepositoryInterface adding a caching layer (Day 24).
 *
 * Implements the Decorator Pattern to wrap EloquentProductRepository and transparently
 * cache public catalog listings and single-product queries with tag- and version-based
 * invalidation.
 */
class CachedProductRepository implements ProductRepositoryInterface
{
    public function __construct(
        private readonly EloquentProductRepository $inner,
        private readonly CatalogCacheService $cache,
    ) {}

    /** @return iterable<int, Product> */
    public function forVendor(int $userId): iterable
    {
        return $this->inner->forVendor($userId);
    }

    /**
     * Paginate the products that are publicly visible in the catalog with caching.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginateVisible(array $filters, int $perPage, string $sort): LengthAwarePaginator
    {
        $page = Paginator::resolveCurrentPage();

        return $this->cache->rememberCatalogListing(
            $filters,
            $perPage,
            $sort,
            $page,
            fn (): LengthAwarePaginator => $this->inner->paginateVisible($filters, $perPage, $sort)
        );
    }

    /** Return a publicly visible product with caching, or abort with a 404. */
    public function findVisible(int $id): Product
    {
        return $this->cache->rememberVisibleProduct(
            $id,
            fn (): Product => $this->inner->findVisible($id)
        );
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Product
    {
        $product = $this->inner->create($attributes);
        $this->cache->invalidateCatalog();

        return $product;
    }

    /** @param array<string, mixed> $attributes */
    public function update(Product $product, array $attributes): Product
    {
        $updated = $this->inner->update($product, $attributes);
        $this->cache->invalidateCatalog();

        return $updated;
    }

    public function delete(Product $product): void
    {
        $this->inner->delete($product);
        $this->cache->invalidateCatalog();
    }
}
