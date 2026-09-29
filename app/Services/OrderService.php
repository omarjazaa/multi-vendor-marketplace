<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\EmptyCartException;
use App\Exceptions\FraudRiskException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidCouponException;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Repositories\Contracts\CartRepositoryInterface;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Services\Checkout\CheckoutContext;
use App\Services\Checkout\CheckoutValidationPipeline;
use App\Services\Checkout\ProportionalAllocator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly CartRepositoryInterface $carts,
        private readonly InventoryRepositoryInterface $inventories,
        private readonly PricingService $pricing,
        private readonly ProportionalAllocator $allocator,
        private readonly CheckoutValidationPipeline $pipeline,
    ) {}

    /**
     * Convert a filled cart into per-vendor orders (Day 15 split), priced and
     * guarded by the Day 16 rules:
     *
     *  1. the checkout validation chain — stock, coupon, fraud — reads live
     *     state and rejects before anything is written;
     *  2. a single transaction reserves stock with the atomic guarded
     *     decrement, prices the cart with the Decorator pipeline, allocates
     *     the cart-wide discount and tax across the vendor split (largest
     *     remainder) and writes one order per store plus the cart clearing.
     *
     * A single failure rolls back the stock moves, all split orders and the
     * cart clearing together — it is all-or-nothing.
     *
     * @return Collection<int, Order> orders grouped by vendor, in first-seen store order
     *
     * @throws EmptyCartException when the cart holds no lines
     * @throws InsufficientStockException when a line outran available stock
     * @throws InvalidCouponException when the supplied coupon code is rejected
     * @throws FraudRiskException when a fraud heuristic rejects the checkout
     */
    public function place(Cart $cart, ?string $paymentMethod, ?string $couponCode = null): Collection
    {
        $lines = $cart->items()->with('product')->get();

        if ($lines->isEmpty()) {
            throw new EmptyCartException;
        }

        $context = new CheckoutContext($cart, $lines, $couponCode, $paymentMethod);
        $this->pipeline->run($context);

        return DB::transaction(function () use ($context): Collection {
            foreach ($context->lines as $line) {
                if (! $this->inventories->decrementQuantity($line->product, $line->quantity)) {
                    $available = $this->inventories->firstOrCreateForProduct($line->product)->quantity;

                    throw new InsufficientStockException($available, $line->product->name);
                }
            }

            return $this->writeSplitOrders($context);
        });
    }

    /**
     * Price the cart once, then slice it into per-vendor orders whose money
     * columns sum back exactly to the summary: each order stores its own
     * subtotal-derived share of the cart-wide discount and tax plus the
     * resulting total.
     *
     * @return Collection<int, Order> orders in first-seen store order
     */
    private function writeSplitOrders(CheckoutContext $context): Collection
    {
        $summary = $this->pricing->summarize($context->cart, $context->couponCode);

        $groups = $context->lines
            ->groupBy(fn (CartItem $line): int => (int) $line->product->store_id)
            ->values();

        $subtotals = $groups
            ->map(fn (Collection $vendorLines): float => round(
                (float) $vendorLines->sum(
                    fn (CartItem $line): float => (float) $line->unit_price * (int) $line->quantity,
                ),
                2,
            ))
            ->all();

        $discounts = $this->allocator->split($summary->discount, $subtotals);
        $taxes = $this->allocator->split($summary->tax, $subtotals);

        $status = OrderStatus::from((string) config('marketplace.checkout.default_status'));

        $orders = $groups
            ->map(fn (Collection $vendorLines, int $index): Order => $this->orders->create(
                userId: $context->userId(),
                storeId: (int) $vendorLines->first()->product->store_id,
                status: $status,
                paymentMethod: $context->paymentMethod,
                discount: number_format($discounts[$index], 2, '.', ''),
                tax: number_format($taxes[$index], 2, '.', ''),
                totalPrice: number_format(
                    round($subtotals[$index] - $discounts[$index] + $taxes[$index], 2),
                    2,
                    '.',
                    '',
                ),
                items: $vendorLines->map(fn (CartItem $line): array => [
                    'product_id' => $line->product_id,
                    'name' => $line->product->name,
                    'unit_price' => $line->unit_price,
                    'quantity' => $line->quantity,
                ])->all(),
            ))
            ->values();

        $this->carts->clear($context->cart);

        return $orders;
    }

    /** Paginate the orders placed by a customer. */
    public function ordersFor(int $userId): LengthAwarePaginator
    {
        return $this->orders->forUser(
            $userId,
            (int) config('marketplace.checkout.orders_per_page'),
        );
    }

    /** Paginate the order lines a vendor must fulfil, optionally filtered by status. */
    public function linesForVendor(int $userId, ?string $status): LengthAwarePaginator
    {
        return $this->orders->linesForVendor(
            $userId,
            ['status' => $status],
            (int) config('marketplace.orders.vendor_per_page'),
        );
    }

    /** Paginate every order in the system for the admin listing. */
    public function allOrders(?string $status): LengthAwarePaginator
    {
        return $this->orders->allForAdmin(
            ['status' => $status],
            (int) config('marketplace.orders.admin_per_page'),
        );
    }

    /**
     * Move an order to the next status when the configured transition map allows it.
     *
     * The map lives in config/marketplace.php (marketplace.orders.transitions), so
     * lifecycle rules are declared once and shared by every actor. Authorization
     * (who may apply a transition) stays in the controller/policy layer.
     *
     * @throws InvalidOrderTransitionException when the transition is not allowed
     */
    public function transitionTo(Order $order, OrderStatus $target): Order
    {
        $allowed = (array) config("marketplace.orders.transitions.{$order->status->value}", []);

        if (! in_array($target->value, $allowed, true)) {
            throw new InvalidOrderTransitionException($order->status->value, $target->value);
        }

        return $this->orders->updateStatus($order, $target);
    }
}
