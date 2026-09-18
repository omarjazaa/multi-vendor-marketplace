<?php

namespace Database\Factories;

use App\Enums\StoreStatus;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorProfile>
 */
class VendorProfileFactory extends Factory
{
    /**
     * Define the default vendor profile state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'verification_status' => StoreStatus::PENDING,
        ];
    }
}
