<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\EmptyCartException;
use App\Exceptions\FraudRiskException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidCouponException;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlaceOrderRequest;
use App\Http\Resources\OrderResource;
use App\Http\Traits\ApiResponse;
use App\Models\Order;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly OrderService $orders,
        private readonly CartService $cart,
    ) {}

    public function checkout(PlaceOrderRequest $request): JsonResponse
    {
        $cart = $this->cart->forUser((int) $request->user()->id);

        try {
            $orders = $this->orders->place(
                $cart,
                $request->validated('payment_method'),
                $request->validated('coupon'),
            );
        } catch (EmptyCartException) {
            return $this->errorResponse(
                'Your cart is empty.',
                ['cart' => 'Add at least one item before checking out.'],
                422,
            );
        } catch (InvalidCouponException $exception) {
            // Same envelope as the cart-summary preview so clients handle both
            // surfaces with one code path.
            return $this->errorResponse(
                'Coupon cannot be applied.',
                ['coupon' => $exception->getMessage()],
                422,
            );
        } catch (FraudRiskException $exception) {
            // Name the exact rule that tripped so support can trace a block.
            return $this->errorResponse(
                "Checkout rejected by rule {$exception->rule}.",
                ['fraud' => $exception->getMessage()],
                422,
            );
        } catch (InsufficientStockException $exception) {
            return $this->errorResponse(
                'Insufficient stock during checkout.',
                ['quantity' => "Only {$exception->available} units are available for {$exception->productName}."],
                409,
            );
        }

        // One order per vendor (Day 15): a single-store cart still returns a
        // one-element array so clients always read data.orders.
        return $this->successResponse(
            ['orders' => OrderResource::collection($orders)->resolve($request)],
            $orders->count() > 1 ? 'Orders placed.' : 'Order placed.',
            201,
        );
    }

    public function index(Request $request): JsonResponse
    {
        $orders = $this->orders->ordersFor((int) $request->user()->id);

        return $this->successResponse([
            'orders' => $this->paginatedOrders($orders, $request),
        ]);
    }

    public function show(Order $order): JsonResponse
    {
        Gate::authorize('view', $order);

        return $this->successResponse([
            'order' => OrderResource::make($order->load('items')),
        ]);
    }

    /** Shape the paginator as { data, links, meta } inside the API envelope. */
    private function paginatedOrders(LengthAwarePaginator $orders, Request $request): array
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
