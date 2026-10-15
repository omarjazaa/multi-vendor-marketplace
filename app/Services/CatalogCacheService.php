<?php

namespace App\Services;

use App\Models\Product;
use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Service for caching category trees and public product catalog listings (Day 24).
 *
 * Coordinates cache keys, TTLs, Redis tags when supported, and version-based O(1)
 * invalidation so that all cache stores (Redis, Array, File, Database) remain strictly consistent.
 */
class CatalogCacheService
{
    public function __construct(private readonly CacheRepository $cache) {}

    /**
     * Cache and return the category tree.
     */
    public function rememberCategoryTree(Closure $callback): iterable
    {
        if (! $this->isEnabled()) {
            return $callback();
        }

        $key = (string) config('marketplace.cache.category_tree_key', 'categories:tree');
        $ttl = (int) config('marketplace.cache.categories_ttl', 3600);

        if ($this->cache->supportsTags()) {
            return $this->cache->tags([(string) config('marketplace.cache.categories_tag', 'categories')])
                ->remember($key, $ttl, $callback);
        }

        return $this->cache->remember($key, $ttl, $callback);
    }

    /**
     * Cache and return a paginated catalog listing.
     *
     * @param  array<string, mixed>  $filters
     */
    public function rememberCatalogListing(
        array $filters,
        int $perPage,
        string $sort,
        int $page,
        Closure $callback,
    ): LengthAwarePaginator {
        if (! $this->isEnabled()) {
            return $callback();
        }

        ksort($filters);
        $version = $this->getCatalogVersion();
        $signature = sha1(serialize([$filters, $perPage, $sort, $page]));
        $key = "catalog:v{$version}:listing:{$signature}";
        $ttl = (int) config('marketplace.cache.catalog_ttl', 1800);

        if ($this->cache->supportsTags()) {
            return $this->cache->tags([(string) config('marketplace.cache.catalog_tag', 'catalog')])
                ->remember($key, $ttl, $callback);
        }

        return $this->cache->remember($key, $ttl, $callback);
    }

    /**
     * Cache and return a single publicly visible product.
     */
    public function rememberVisibleProduct(int $id, Closure $callback): Product
    {
        if (! $this->isEnabled()) {
            return $callback();
        }

        $version = $this->getCatalogVersion();
        $key = "catalog:v{$version}:product:{$id}";
        $ttl = (int) config('marketplace.cache.catalog_ttl', 1800);

        if ($this->cache->supportsTags()) {
            return $this->cache->tags([(string) config('marketplace.cache.catalog_tag', 'catalog')])
                ->remember($key, $ttl, $callback);
        }

        return $this->cache->remember($key, $ttl, $callback);
    }

    /**
     * Invalidate category tree cache.
     */
    public function invalidateCategories(): void
    {
        $key = (string) config('marketplace.cache.category_tree_key', 'categories:tree');

        if ($this->cache->supportsTags()) {
            $this->cache->tags([(string) config('marketplace.cache.categories_tag', 'categories')])->flush();
        }

        $this->cache->forget($key);
    }

    /**
     * Invalidate product catalog cache (listings and individual products).
     */
    public function invalidateCatalog(): void
    {
        if ($this->cache->supportsTags()) {
            $this->cache->tags([(string) config('marketplace.cache.catalog_tag', 'catalog')])->flush();
        }

        $versionKey = (string) config('marketplace.cache.catalog_version_key', 'catalog:version');
        $current = (int) $this->cache->get($versionKey, 1);
        $this->cache->forever($versionKey, $current + 1);
    }

    /**
     * Invalidate all catalog and category caches.
     */
    public function invalidateAll(): void
    {
        $this->invalidateCategories();
        $this->invalidateCatalog();
    }

    /**
     * Get the current catalog cache version number for cache key partitioning.
     */
    public function getCatalogVersion(): int
    {
        $versionKey = (string) config('marketplace.cache.catalog_version_key', 'catalog:version');

        return (int) $this->cache->get($versionKey, 1);
    }

    /**
     * Determine whether the catalog cache layer is enabled.
     */
    public function isEnabled(): bool
    {
        return (bool) config('marketplace.cache.enabled', true);
    }
}
