<?php

namespace App\Repositories\Contracts;

use App\Enums\StoreStatus;
use App\Models\Store;
use App\Models\VendorProfile;

interface StoreRepositoryInterface
{
    /**
     * Find the vendor profile belonging to a user.
     */
    public function findVendorProfileByUserId(int $userId): ?VendorProfile;

    /**
     * Create a pending vendor profile.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createVendorProfile(array $attributes): VendorProfile;

    /**
     * Create a store application for a vendor profile.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Store;

    /**
     * Update a store and its vendor profile with the review decision.
     */
    public function updateReviewStatus(Store $store, StoreStatus $status): Store;
}
