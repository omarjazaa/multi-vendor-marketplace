<?php

namespace App\Http\Resources;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Services\ShipmentTrackingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Shipment */
class ShipmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $statusEnum = $this->status instanceof ShipmentStatus
            ? $this->status
            : ShipmentStatus::tryFrom((string) $this->status) ?? ShipmentStatus::PROCESSING;

        return [
            'id' => $this->id,
            'order_item_id' => $this->order_item_id,
            'order_id' => $this->orderItem?->order_id,
            'tracking_number' => $this->tracking_number,
            'status' => $statusEnum->value,
            'status_label' => $statusEnum->label(),
            'status_icon' => $statusEnum->icon(),
            'is_delivered' => $statusEnum === ShipmentStatus::DELIVERED,
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'product' => $this->whenLoaded('orderItem', function () {
                return [
                    'name' => $this->orderItem?->product?->name ?? $this->orderItem?->name,
                    'quantity' => $this->orderItem?->quantity,
                    'unit_price' => $this->orderItem?->unit_price,
                ];
            }),
            'timeline' => app(ShipmentTrackingService::class)->buildTrackingTimeline($this->resource),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
