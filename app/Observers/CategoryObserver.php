<?php

namespace App\Observers;

use App\Models\Category;
use App\Services\CatalogCacheService;

class CategoryObserver
{
    public function __construct(private readonly CatalogCacheService $cache) {}

    public function saved(Category $category): void
    {
        $this->cache->invalidateCategories();
        $this->cache->invalidateCatalog();
    }

    public function deleted(Category $category): void
    {
        $this->cache->invalidateCategories();
        $this->cache->invalidateCatalog();
    }
}
