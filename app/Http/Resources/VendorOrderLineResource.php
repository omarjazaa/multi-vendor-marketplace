<?php

namespace App\Http\Resources;

use App\Enums\ShipmentStatus;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A vendor-facing order line: the snapshot of what to fulfil plus the order and
 * buyer context needed to act on it, without leaking the full receipt.
 *
 * @mixin OrderItem
 */
class VendorOrderLineResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'order_status' => $this->order->status->value,
            'order_created_at' => $this->order->created_at?->toIso8601String(),
            'buyer' => [
                'id' => $this->order->user->id,
                'name' => $this->order->user->name,
            ],
            'product_id' => $this->product_id,
            'name' => $this->name,
            'unit_price' => $this->unit_price,
            'quantity' => $this->quantity,
            'line_total' => number_format((float) $this->unit_price * $this->quantity, 2, '.', ''),
            'shipment' => $this->shipment ? [
                'id' => $this->shipment->id,
                'tracking_number' => $this->shipment->tracking_number,
                'status' => $this->shipment->status instanceof ShipmentStatus ? $this->shipment->status->value : (string) $this->shipment->status,
                'delivered_at' => $this->shipment->delivered_at?->toIso8601String(),
            ] : null,
        ];
    }
}
