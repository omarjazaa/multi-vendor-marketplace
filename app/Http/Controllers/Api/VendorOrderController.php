<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\FilterOrdersRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\VendorOrderLineResource;
use App\Http\Traits\ApiResponse;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class VendorOrderController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly OrderService $orders) {}

    /**
     * List the order lines belonging to the caller's stores (role:vendor),
     * optionally filtered by ?status=. Rows are scoped in the repository, so
     * vendors only ever receive their own lines — no per-row policy check.
     */
    public function index(FilterOrdersRequest $request): JsonResponse
    {
        $lines = $this->orders->linesForVendor(
            (int) $request->user()->id,
            $request->validated('status'),
        );

        return $this->successResponse([
            'lines' => $this->paginatedLines($lines, $request),
        ]);
    }

    /** Transition the status of an order the vendor has lines in. */
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        Gate::authorize('update', $order);

        try {
            $order = $this->orders->transitionTo(
                $order,
                OrderStatus::from($request->validated('status')),
            );
        } catch (InvalidOrderTransitionException $exception) {
            return $this->errorResponse(
                'Order status transition not allowed.',
                ['status' => "An order cannot move from {$exception->fromStatus} to {$exception->toStatus}."],
                409,
            );
        }

        return $this->successResponse(
            ['order' => OrderResource::make($order->load('items'))],
            'Order status updated.',
        );
    }

    /** Shape the paginator as { data, links, meta } inside the API envelope. */
    private function paginatedLines(LengthAwarePaginator $lines, FilterOrdersRequest $request): array
    {
        $paginated = $lines->toArray();

        return [
            'data' => VendorOrderLineResource::collection($lines->getCollection())->resolve($request),
            'links' => [
                'first' => $paginated['first_page_url'],
                'last' => $paginated['last_page_url'],
                'prev' => $paginated['prev_page_url'],
                'next' => $paginated['next_page_url'],
            ],
            'meta' => Arr::except($paginated, [
                'data', 'first_page_url', 'last_page_url', 'prev_page_url', 'next_page_url',
            ]),
        ];
    }
}
