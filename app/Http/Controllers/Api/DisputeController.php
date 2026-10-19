<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\OrderDisputeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateDisputeRequest;
use App\Http\Requests\FilterDisputesRequest;
use App\Http\Resources\DisputeResource;
use App\Http\Traits\ApiResponse;
use App\Models\Order;
use App\Services\DisputeRefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DisputeController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly DisputeRefundService $disputes) {}

    /** List the authenticated customer's own disputes. */
    public function index(FilterDisputesRequest $request): JsonResponse
    {
        $disputes = $this->disputes->forCustomer($request->user()->id);

        return $this->successResponse([
            'disputes' => DisputeResource::collection($disputes)->response()->getData(true),
        ]);
    }

    /** List disputes for a specific order owned by customer. */
    public function orderDisputes(Order $order): JsonResponse
    {
        Gate::authorize('view', $order);

        $disputes = $this->disputes->forOrder($order->id);

        return $this->successResponse([
            'disputes' => DisputeResource::collection($disputes)->response()->getData(true),
        ]);
    }

    /** File a dispute against an eligible order (customer only). */
    public function store(CreateDisputeRequest $request, Order $order): JsonResponse
    {
        Gate::authorize('view', $order);

        try {
            $dispute = $this->disputes->createDispute(
                $order,
                $request->user(),
                $request->validated('reason'),
            );
        } catch (OrderDisputeException $exception) {
            return $this->errorResponse(
                $exception->getMessage(),
                ['dispute' => $exception->getMessage()],
                $exception->getCode() ?: 409,
            );
        }

        return $this->successResponse(
            ['dispute' => DisputeResource::make($dispute->load('order'))],
            'Dispute opened successfully.',
            201,
        );
    }
}
