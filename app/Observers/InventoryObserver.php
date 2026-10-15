<?php

namespace App\Observers;

use App\Models\Inventory;
use App\Services\CatalogCacheService;

class InventoryObserver
{
    public function __construct(private readonly CatalogCacheService $cache) {}

    public function saved(Inventory $inventory): void
    {
        $this->cache->invalidateCatalog();
    }
}
