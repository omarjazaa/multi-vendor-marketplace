<?php

namespace App\Http\Resources;

use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Cart */
class CartResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'items' => $this->whenLoaded('items', fn () => CartItemResource::collection($this->items)),
            'total_quantity' => $this->whenLoaded(
                'items',
                fn (): int => (int) $this->items->sum('quantity'),
            ),
            'total_price' => $this->whenLoaded(
                'items',
                fn (): string => number_format(
                    (float) $this->items->sum(
                        fn ($item): float => (float) $item->unit_price * $item->quantity,
                    ),
                    2,
                    '.',
                    '',
                ),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
