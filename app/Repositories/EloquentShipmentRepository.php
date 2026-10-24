<?php

namespace App\Repositories;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Repositories\Contracts\ShipmentRepositoryInterface;
use Illuminate\Support\Collection;

final class EloquentShipmentRepository implements ShipmentRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function findByTrackingNumber(string $trackingNumber): ?Shipment
    {
        return Shipment::with(['orderItem.product', 'orderItem.order.user', 'orderItem.order.store'])
            ->where('tracking_number', $trackingNumber)
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function findByOrderItemId(int $orderItemId): ?Shipment
    {
        return Shipment::with(['orderItem.product', 'orderItem.order'])
            ->where('order_item_id', $orderItemId)
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function forOrder(int $orderId): Collection
    {
        return Shipment::with(['orderItem.product'])
            ->whereHas('orderItem', fn ($query) => $query->where('order_id', $orderId))
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function create(int $orderItemId, string $trackingNumber, ShipmentStatus $status = ShipmentStatus::PROCESSING): Shipment
    {
        return Shipment::create([
            'order_item_id' => $orderItemId,
            'tracking_number' => $trackingNumber,
            'status' => $status,
            'shipped_at' => now(),
            'delivered_at' => null,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function updateStatus(Shipment $shipment, ShipmentStatus $status): Shipment
    {
        $attributes = ['status' => $status];

        if ($status === ShipmentStatus::DELIVERED && $shipment->delivered_at === null) {
            $attributes['delivered_at'] = now();
        }

        $shipment->update($attributes);

        return $shipment->fresh(['orderItem.product', 'orderItem.order']);
    }
}
