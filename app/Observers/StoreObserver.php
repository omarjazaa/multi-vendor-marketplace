<?php

namespace App\Observers;

use App\Models\Store;
use App\Services\CatalogCacheService;

class StoreObserver
{
    public function __construct(private readonly CatalogCacheService $cache) {}

    public function updated(Store $store): void
    {
        if ($store->wasChanged('status')) {
            $this->cache->invalidateCatalog();
        }
    }
}
