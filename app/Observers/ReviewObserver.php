<?php

namespace App\Observers;

use App\Models\Review;
use App\Services\CatalogCacheService;

class ReviewObserver
{
    public function __construct(private readonly CatalogCacheService $cache) {}

    public function saved(Review $review): void
    {
        $this->cache->invalidateCatalog();
    }

    public function deleted(Review $review): void
    {
        $this->cache->invalidateCatalog();
    }
}
