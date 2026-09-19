<?php

namespace App\Repositories;

use App\Enums\StoreStatus;
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

    /**
     * Persist a review decision across the store and vendor profile records.
     */
    public function updateReviewStatus(Store $store, StoreStatus $status): Store
    {
        $store->update(['status' => $status]);
        $store->vendorProfile()->update([
            'verification_status' => $status,
            'verified_at' => $status === StoreStatus::APPROVED ? now() : null,
        ]);

        return $store->fresh(['vendorProfile.user']);
    }
}
