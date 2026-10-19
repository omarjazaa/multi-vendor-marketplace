<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransitionException;
use App\Exceptions\RefundProcessingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\FilterOrdersRequest;
use App\Http\Requests\RefundOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Http\Traits\ApiResponse;
use App\Models\Order;
use App\Services\DisputeRefundService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class AdminOrderController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly OrderService $orders) {}

    /** List every order in the system (role:admin), optionally filtered by ?status=. */
    public function index(FilterOrdersRequest $request): JsonResponse
    {
        $orders = $this->orders->allOrders($request->validated('status'));

        return $this->successResponse([
            'orders' => $this->paginatedOrders($orders, $request),
        ]);
    }

    /** Transition the status of any order (role:admin). */
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

    /** Refund an order through its polymorphic payment gateway (role:admin, Day 27). */
    public function refund(
        RefundOrderRequest $request,
        Order $order,
        DisputeRefundService $disputeRefundService,
    ): JsonResponse {
        Gate::authorize('update', $order);

        try {
            $refundedOrder = $disputeRefundService->refundOrder(
                order: $order,
                reason: $request->validated('reason'),
                force: (bool) $request->validated('force', false),
                admin: $request->user(),
            );
        } catch (InvalidOrderTransitionException $exception) {
            return $this->errorResponse(
                'Order status transition not allowed.',
                ['status' => "An order cannot move from {$exception->fromStatus} to {$exception->toStatus}."],
                409,
            );
        } catch (RefundProcessingException $exception) {
            return $this->errorResponse(
                'Payment gateway refund failed.',
                ['gateway' => $exception->getMessage()],
                422,
            );
        }

        return $this->successResponse(
            [
                'order' => OrderResource::make($refundedOrder->load('items')),
                'refund' => [
                    'successful' => true,
                    'reference' => $refundedOrder->refund_reference,
                    'reason' => $refundedOrder->refund_reason,
                ],
            ],
            'Order refunded successfully.',
        );
    }

    /** Shape the paginator as { data, links, meta } inside the API envelope. */
    private function paginatedOrders(LengthAwarePaginator $orders, FilterOrdersRequest $request): array
    {
        $paginated = $orders->toArray();

        return [
            'data' => OrderResource::collection($orders->getCollection())->resolve($request),
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
