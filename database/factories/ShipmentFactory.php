<?php

namespace Database\Factories;

use App\Models\OrderItem;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Shipment> */
class ShipmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'order_item_id' => OrderItem::factory(),
            'tracking_number' => 'TRK-'.fake()->unique()->numerify('#####-#####'),
            'status' => 'processing',
            'shipped_at' => now(),
            'delivered_at' => null,
        ];
    }
}
