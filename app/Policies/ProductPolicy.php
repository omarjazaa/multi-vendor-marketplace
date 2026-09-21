<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\Store;
use App\Models\User;

class ProductPolicy
{
    /**
     * Determine whether a vendor may create a product in the store.
     */
    public function create(User $user, Store $store): bool
    {
        return $this->ownsStore($user, $store);
    }

    /**
     * Determine whether a vendor owns the product's store.
     */
    public function update(User $user, Product $product): bool
    {
        return $this->ownsStore($user, $product->store);
    }

    /**
     * Determine whether a vendor may delete the product.
     */
    public function delete(User $user, Product $product): bool
    {
        return $this->ownsStore($user, $product->store);
    }

    private function ownsStore(User $user, Store $store): bool
    {
        return $user->hasRole('vendor')
            && $store->vendorProfile?->user_id === $user->id;
    }
}
