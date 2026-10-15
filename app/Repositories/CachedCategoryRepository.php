<?php

namespace App\Repositories;

use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Services\CatalogCacheService;

/**
 * Decorator for CategoryRepositoryInterface adding a caching layer (Day 24).
 *
 * Implements the Decorator Pattern to wrap EloquentCategoryRepository and transparently
 * cache the hierarchical category tree with tag- and key-based invalidation.
 */
class CachedCategoryRepository implements CategoryRepositoryInterface
{
    public function __construct(
        private readonly EloquentCategoryRepository $inner,
        private readonly CatalogCacheService $cache,
    ) {}

    /** @return iterable<int, Category> */
    public function all(): iterable
    {
        return $this->cache->rememberCategoryTree(
            fn (): iterable => $this->inner->all()
        );
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Category
    {
        $category = $this->inner->create($attributes);
        $this->cache->invalidateCategories();
        $this->cache->invalidateCatalog();

        return $category;
    }

    /** @param array<string, mixed> $attributes */
    public function update(Category $category, array $attributes): Category
    {
        $updated = $this->inner->update($category, $attributes);
        $this->cache->invalidateCategories();
        $this->cache->invalidateCatalog();

        return $updated;
    }

    public function delete(Category $category): void
    {
        $this->inner->delete($category);
        $this->cache->invalidateCategories();
        $this->cache->invalidateCatalog();
    }
}
