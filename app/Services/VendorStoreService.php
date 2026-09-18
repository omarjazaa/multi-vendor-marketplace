<?php

namespace App\Services;

use App\Enums\StoreStatus;
use App\Exceptions\StoreApplicationAlreadyExists;
use App\Models\Store;
use App\Models\User;
use App\Repositories\Contracts\StoreRepositoryInterface;
use Illuminate\Database\DatabaseManager;

class VendorStoreService
{
    public function __construct(
        private readonly StoreRepositoryInterface $stores,
        private readonly DatabaseManager $database,
    ) {}

    /**
     * Create a vendor profile and pending store application atomically.
     *
     * @param  array{name: string, slug: string, description?: string|null}  $attributes
     *
     * @throws StoreApplicationAlreadyExists
     */
    public function apply(User $vendor, array $attributes): Store
    {
        if ($this->stores->findVendorProfileByUserId($vendor->id)) {
            throw new StoreApplicationAlreadyExists('A store application already exists for this vendor.');
        }

        return $this->database->transaction(function () use ($vendor, $attributes): Store {
            $profile = $this->stores->createVendorProfile([
                'user_id' => $vendor->id,
                'verification_status' => StoreStatus::PENDING,
            ]);

            return $this->stores->create([
                'vendor_profile_id' => $profile->id,
                'name' => $attributes['name'],
                'slug' => $attributes['slug'],
                'description' => $attributes['description'] ?? null,
                'status' => StoreStatus::PENDING,
            ]);
        });
    }
}
