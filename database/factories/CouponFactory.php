<?php

namespace Database\Factories;

use App\Enums\DiscountType;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Coupon> */
class CouponFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('??-####')),
            'type' => DiscountType::PERCENTAGE,
            'value' => fake()->randomFloat(2, 5, 50),
            'expires_at' => now()->addDays(30),
            'usage_limit' => null,
            'used_count' => 0,
            'min_order_amount' => null,
            'created_by' => null,
        ];
    }

    /** Build a coupon that takes a fixed amount instead of a percentage. */
    public function fixed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => DiscountType::FIXED,
            'value' => fake()->randomFloat(2, 5, 50),
        ]);
    }
}
