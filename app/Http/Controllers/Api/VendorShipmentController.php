<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ShipOrderRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ShipmentResource;
use App\Http\Traits\ApiResponse;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderService;
use App\Services\ShipmentTrackingService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Vendor fulfilment: per-vendor orders belong to one store, so "ship item" is
 * shipped as a whole — one action per order. The state matrix is the only gate
 * that touches order status; this controller owns HTTP.
 */
class VendorShipmentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly OrderService $orders,
        private readonly ShipmentTrackingService $tracking,
    ) {}

    /**
     * Ship the caller's own order: state-gate it to shipped (firing
     * OrderShipped exactly once through the shared gate), then record the mock
     * tracking number on the shipment row.
     *
     * @throws ModelNotFoundException when the line does not belong to the order
     */
    public function ship(ShipOrderRequest $request, Order $order, OrderItem $item): JsonResponse
    {
        Gate::authorize('update', $order);

        if ((int) $item->order_id !== (int) $order->id) {
            throw (new ModelNotFoundException)->setModel(OrderItem::class, [$item->id]);
        }

        try {
            $order = $this->orders->transitionTo($order, OrderStatus::SHIPPED);
        } catch (InvalidOrderTransitionException $exception) {
            return $this->errorResponse(
                'Order cannot be shipped in its current state.',
                ['status' => "An order cannot move from {$exception->fromStatus} to shipped."],
                409,
            );
        }

        $shipment = $this->tracking->record(
            (int) $order->id,
            (int) $item->id,
            $this->tracking->generate((int) $order->id, (int) $item->id),
        );

        return $this->successResponse(
            [
                'shipment' => ShipmentResource::make($shipment),
                'order' => OrderResource::make($order->load('items')),
            ],
            'Order shipped.',
            200,
        );
    }
}
