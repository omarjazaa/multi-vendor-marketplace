<?php

namespace App\Observers;

use App\Models\ProductImage;
use App\Services\CatalogCacheService;

class ProductImageObserver
{
    public function __construct(private readonly CatalogCacheService $cache) {}

    public function saved(ProductImage $image): void
    {
        $this->cache->invalidateCatalog();
    }

    public function deleted(ProductImage $image): void
    {
        $this->cache->invalidateCatalog();
    }
}
