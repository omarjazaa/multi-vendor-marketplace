<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\CatalogCacheService;

class ProductObserver
{
    public function __construct(private readonly CatalogCacheService $cache) {}

    public function saved(Product $product): void
    {
        $this->cache->invalidateCatalog();
    }

    public function deleted(Product $product): void
    {
        $this->cache->invalidateCatalog();
    }
}
