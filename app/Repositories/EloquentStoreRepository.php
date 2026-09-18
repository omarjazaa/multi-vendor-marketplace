<?php

namespace App\Repositories;

use App\Models\Store;
use App\Models\VendorProfile;
use App\Repositories\Contracts\StoreRepositoryInterface;

class EloquentStoreRepository implements StoreRepositoryInterface
{
    /**
     * Retrieve a vendor profile by its owning user.
     */
    public function findVendorProfileByUserId(int $userId): ?VendorProfile
    {
        return VendorProfile::where('user_id', $userId)->first();
    }

    /**
     * Persist a vendor profile through Eloquent.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createVendorProfile(array $attributes): VendorProfile
    {
        return VendorProfile::create($attributes);
    }

    /**
     * Persist a store application through Eloquent.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Store
    {
        return Store::create($attributes);
    }
}
