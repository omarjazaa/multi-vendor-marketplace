<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShipmentResource;
use App\Http\Traits\ApiResponse;
use App\Models\Order;
use App\Services\ShipmentTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ShipmentTrackingController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ShipmentTrackingService $trackingService
    ) {}

    /**
     * Public / Universal carrier tracking endpoint.
     *
     * Enables customers, vendors, and guests to track any package by its tracking number.
     */
    public function track(string $trackingNumber): JsonResponse
    {
        $shipment = $this->trackingService->findByTrackingNumber($trackingNumber);

        if ($shipment === null) {
            return $this->errorResponse('Tracking number not found.', [
                'tracking_number' => ['No shipment found matching the provided tracking reference.'],
            ], 404);
        }

        return $this->successResponse([
            'shipment' => ShipmentResource::make($shipment),
        ], 'Shipment tracking information retrieved successfully.');
    }

    /**
     * List all shipments and tracking numbers for a specific order.
     */
    public function forOrder(Order $order, Request $request): JsonResponse
    {
        Gate::authorize('view', $order);

        $shipments = $this->trackingService->forOrder((int) $order->id);

        return $this->successResponse([
            'order_id' => $order->id,
            'shipments' => ShipmentResource::collection($shipments),
        ], 'Order shipments retrieved successfully.');
    }
}
