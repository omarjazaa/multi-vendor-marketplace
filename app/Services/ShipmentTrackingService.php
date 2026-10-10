<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Shipment;

/** Deterministic mock tracking provider (Day 21). */
class ShipmentTrackingService
{
    public function __construct(private readonly ?string $prefix = null) {}

    /**
     * Generate the mock carrier reference for one order+line.
     *
     * Deterministic (no randomness, reproducible for the same order item) and
     * config-driven — the prefix comes from marketplace.shipments.tracking_prefix
     * with the conventional TRK default. Format "<prefix>-<order>-<item>".
     *
     * @param  int  $orderId  the order the line belongs to
     * @param  int  $orderItemId  the order line being shipped
     */
    public function generate(int $orderId, int $orderItemId): string
    {
        $prefix = $this->prefix ?? config('marketplace.shipments.tracking_prefix', 'TRK');

        return "{$prefix}-{$orderId}-{$orderItemId}";
    }

    /**
     * Record the tracker for a shipped order+line inside the lifecycle gate's
     * write, so the queued shipment listener always reads the number from the
     * row, not from the event payload.
     */
    public function record(int $orderId, int $orderItemId, string $trackingNumber): Shipment
    {
        return Shipment::create([
            'order_item_id' => $orderItemId,
            'tracking_number' => $trackingNumber,
            'status' => 'processing',
            'shipped_at' => now(),
        ]);
    }

    /** Look up the tracker for the given line (returns null when the order never shipped). */
    public function findByOrderItem(OrderItem $item): ?Shipment
    {
        return Shipment::where('order_item_id', $item->id)->first();
    }
}
