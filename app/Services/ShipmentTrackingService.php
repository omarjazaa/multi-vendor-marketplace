<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shipment;
use App\Repositories\Contracts\ShipmentRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * Deterministic mock tracking and carrier fulfilment lifecycle provider.
 */
class ShipmentTrackingService
{
    private readonly ShipmentRepositoryInterface $shipments;

    private readonly OrderTransitionService $transitions;

    private readonly OrderNotificationService $notifications;

    private readonly ?string $prefix;

    public function __construct(
        ShipmentRepositoryInterface|string|null $shipments = null,
        ?OrderTransitionService $transitions = null,
        ?OrderNotificationService $notifications = null,
        ?string $prefix = null,
    ) {
        if (is_string($shipments)) {
            $this->prefix = $shipments;
            $this->shipments = app(ShipmentRepositoryInterface::class);
        } else {
            $this->shipments = $shipments ?? app(ShipmentRepositoryInterface::class);
            $this->prefix = $prefix;
        }

        $this->transitions = $transitions ?? app(OrderTransitionService::class);
        $this->notifications = $notifications ?? app(OrderNotificationService::class);
    }

    /**
     * Generate the mock carrier reference for one order+line.
     *
     * Deterministic (no randomness, reproducible for the same order item) and
     * config-driven — format "<prefix>-<order>-<item>".
     */
    public function generate(int $orderId, int $orderItemId): string
    {
        $prefix = $this->prefix ?? config('marketplace.shipments.tracking_prefix', 'TRK');

        return "{$prefix}-{$orderId}-{$orderItemId}";
    }

    /**
     * Record the tracker for a shipped order+line.
     */
    public function record(int $orderId, int $orderItemId, string $trackingNumber): Shipment
    {
        return $this->shipments->create($orderItemId, $trackingNumber, ShipmentStatus::PROCESSING);
    }

    /**
     * Look up the tracker for the given order line.
     */
    public function findByOrderItem(OrderItem $item): ?Shipment
    {
        return $this->shipments->findByOrderItemId((int) $item->id);
    }

    /**
     * Look up the shipment by its tracking number.
     */
    public function findByTrackingNumber(string $trackingNumber): ?Shipment
    {
        return $this->shipments->findByTrackingNumber($trackingNumber);
    }

    /**
     * Retrieve all shipments for a given order.
     *
     * @return Collection<int, Shipment>
     */
    public function forOrder(int $orderId): Collection
    {
        return $this->shipments->forOrder($orderId);
    }

    /**
     * Advance the shipment to a target lifecycle status.
     *
     * If the shipment reaches DELIVERED and all other lines for the order are also
     * delivered, the underlying Order is automatically transitioned to DELIVERED,
     * unlocking customer product reviews and notifying the customer.
     */
    public function updateStatus(Shipment $shipment, ShipmentStatus $status): Shipment
    {
        $updated = $this->shipments->updateStatus($shipment, $status);

        if ($status === ShipmentStatus::DELIVERED) {
            $order = $shipment->orderItem?->order ?? Order::find($shipment->orderItem?->order_id);

            if ($order !== null) {
                $this->notifications->notifyDelivered($order, $updated);

                if ($this->areAllOrderLinesDelivered($order)) {
                    if ($order->status === OrderStatus::SHIPPED) {
                        $this->transitions->apply($order, OrderStatus::DELIVERED);
                    }
                }
            }
        }

        return $updated;
    }

    /**
     * Determine whether all items in an order have been fulfilled and delivered.
     */
    public function areAllOrderLinesDelivered(Order $order): bool
    {
        $order->loadMissing('items.shipment');

        return $order->items->every(function (OrderItem $item): bool {
            return $item->shipment !== null && $item->shipment->status === ShipmentStatus::DELIVERED;
        });
    }

    /**
     * Build an informative milestone timeline for parcel tracking.
     *
     * @return list<array{step: int, status: string, title: string, description: string, icon: string, completed: bool, current: bool, timestamp: ?string}>
     */
    public function buildTrackingTimeline(Shipment $shipment): array
    {
        $current = $shipment->status;
        $orderItem = $shipment->orderItem;
        $productName = $orderItem?->product?->name ?? $orderItem?->name ?? 'Ordered Item';

        $steps = [
            ShipmentStatus::PROCESSING->value => 1,
            ShipmentStatus::IN_TRANSIT->value => 2,
            ShipmentStatus::OUT_FOR_DELIVERY->value => 3,
            ShipmentStatus::DELIVERED->value => 4,
        ];

        $currentStep = $steps[$current->value] ?? 1;

        return [
            [
                'step' => 1,
                'status' => ShipmentStatus::PROCESSING->value,
                'title' => 'Order Dispatched & Processed',
                'description' => "Merchant prepared and dispatched {$productName} to the shipping hub.",
                'icon' => '📦',
                'completed' => $currentStep >= 1,
                'current' => $current === ShipmentStatus::PROCESSING,
                'timestamp' => $shipment->shipped_at?->toIso8601String(),
            ],
            [
                'step' => 2,
                'status' => ShipmentStatus::IN_TRANSIT->value,
                'title' => 'In Transit With Carrier',
                'description' => 'Package has departed sorting facility and is en route to the destination city.',
                'icon' => '🚚',
                'completed' => $currentStep >= 2,
                'current' => $current === ShipmentStatus::IN_TRANSIT,
                'timestamp' => $currentStep >= 2 ? ($shipment->updated_at?->toIso8601String()) : null,
            ],
            [
                'step' => 3,
                'status' => ShipmentStatus::OUT_FOR_DELIVERY->value,
                'title' => 'Out for Local Delivery',
                'description' => 'Courier has loaded the parcel and is out for final doorstep delivery.',
                'icon' => '🛵',
                'completed' => $currentStep >= 3,
                'current' => $current === ShipmentStatus::OUT_FOR_DELIVERY,
                'timestamp' => $currentStep >= 3 ? ($shipment->updated_at?->toIso8601String()) : null,
            ],
            [
                'step' => 4,
                'status' => ShipmentStatus::DELIVERED->value,
                'title' => 'Delivered to Recipient',
                'description' => 'Package safely handed over to recipient. Product is now eligible for reviews.',
                'icon' => '✅',
                'completed' => $currentStep >= 4,
                'current' => $current === ShipmentStatus::DELIVERED,
                'timestamp' => $shipment->delivered_at?->toIso8601String(),
            ],
        ];
    }
}
