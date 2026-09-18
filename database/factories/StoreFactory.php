<?php

namespace Database\Factories;

use App\Enums\StoreStatus;
use App\Models\Store;
use App\Models\VendorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    /**
     * Define the default store application state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor_profile_id' => VendorProfile::factory(),
            'name' => fake()->company(),
            'slug' => fake()->unique()->slug(),
            'description' => fake()->sentence(),
            'status' => StoreStatus::PENDING,
        ];
    }
}
