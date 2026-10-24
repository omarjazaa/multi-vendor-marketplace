<?php

namespace App\Repositories\Contracts;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use Illuminate\Support\Collection;

interface ShipmentRepositoryInterface
{
    /**
     * Find a shipment by its tracking number.
     */
    public function findByTrackingNumber(string $trackingNumber): ?Shipment;

    /**
     * Find a shipment by its order item identifier.
     */
    public function findByOrderItemId(int $orderItemId): ?Shipment;

    /**
     * Retrieve all shipments associated with a specific order.
     *
     * @return Collection<int, Shipment>
     */
    public function forOrder(int $orderId): Collection;

    /**
     * Create and record a new shipment.
     */
    public function create(int $orderItemId, string $trackingNumber, ShipmentStatus $status = ShipmentStatus::PROCESSING): Shipment;

    /**
     * Update the shipment status and manage delivery timestamps.
     */
    public function updateStatus(Shipment $shipment, ShipmentStatus $status): Shipment;
}
