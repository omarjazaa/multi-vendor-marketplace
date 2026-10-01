<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            // Null only for rows created before the Day 15 split.
            'store_id' => $this->store_id,
            'status' => $this->status->value,
            'payment_method' => $this->payment_method,
            // Reference issued by the payment strategy (Day 17); null when no
            // method was used or the payment was declined.
            'payment_reference' => $this->payment_reference,
            // This order's share of the cart-wide discount and tax, allocated
            // by largest remainder across the vendor split (Day 16).
            'discount' => $this->discount,
            'tax' => $this->tax,
            'total_price' => $this->total_price,
            'items' => $this->whenLoaded('items', fn () => OrderItemResource::collection($this->items)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
