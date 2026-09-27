<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => OrderStatus::PENDING,
            'payment_method' => fake()->randomElement(['cod', 'card', 'bank_transfer']),
            'total_price' => fake()->randomFloat(2, 10, 1000),
        ];
    }
}
