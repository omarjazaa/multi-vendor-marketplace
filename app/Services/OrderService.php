<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Events\OrderPlaced;
use App\Exceptions\EmptyCartException;
use App\Exceptions\FraudRiskException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidCouponException;
use App\Exceptions\InvalidOrderTransitionException;
use App\Exceptions\UnsupportedPaymentMethodException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Repositories\Contracts\CartRepositoryInterface;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Services\Checkout\CheckoutContext;
use App\Services\Checkout\CheckoutOutcome;
use App\Services\Checkout\CheckoutValidationPipeline;
use App\Services\Checkout\ProportionalAllocator;
use App\Services\Payments\PaymentResult;
use App\Services\Payments\PaymentStrategyFactory;
use App\Services\Payments\PaymentStrategyInterface;
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
        private readonly PaymentStrategyFactory $payments,
        private readonly OrderTransitionService $transitions,
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
     *     remainder) and writes one order per store plus the cart clearing;
     *  3. once that transaction has committed, the payment strategy resolved
     *     by the factory runs once per split order (Day 17);
     *  4. the outcome is broadcast as OrderPlaced so channel strategies can
     *     deliver the customer confirmation and vendor alerts (Day 18).
     *
     * A single failure rolls back the stock moves, all split orders and the
     * cart clearing together — it is all-or-nothing. Steps 3 and 4 run after
     * that commit, so neither a declined payment nor a failed notification
     * channel can ever roll the order back.
     *
     * Payment semantics (deliberately outside the transaction): step 3 runs
     * after the commit because an order must survive its own declined
     * payment. A declined attempt leaves the order, its snapshot lines, the
     * reserved stock and the cleared cart untouched, keeps the order in its
     * initial status and records no reference; the decline is reported on the
     * returned outcome for the API to surface. Recovering a declined order is
     * an operations decision: it is cancelled through the lifecycle map, or
     * the customer pays it another way.
     *
     * @return CheckoutOutcome the per-vendor orders plus their payment results
     *
     * @throws EmptyCartException when the cart holds no lines
     * @throws InsufficientStockException when a line outran available stock
     * @throws InvalidCouponException when the supplied coupon code is rejected
     * @throws FraudRiskException when a fraud heuristic rejects the checkout
     * @throws UnsupportedPaymentMethodException when the method has no configured strategy
     */
    public function place(Cart $cart, ?string $paymentMethod, ?string $couponCode = null): CheckoutOutcome
    {
        $lines = $cart->items()->with('product')->get();

        if ($lines->isEmpty()) {
            throw new EmptyCartException;
        }

        $context = new CheckoutContext($cart, $lines, $couponCode, $paymentMethod);
        $this->pipeline->run($context);

        // Resolve the strategy before anything is written, so a method without
        // a configured strategy can never reach the database. The API rejects
        // unknown methods even earlier, in the request validation.
        $strategy = blank($paymentMethod) ? null : $this->payments->make($paymentMethod);

        $orders = DB::transaction(function () use ($context): Collection {
            foreach ($context->lines as $line) {
                if (! $this->inventories->decrementQuantity($line->product, $line->quantity)) {
                    $available = $this->inventories->firstOrCreateForProduct($line->product)->quantity;

                    throw new InsufficientStockException($available, $line->product->name);
                }
            }

            return $this->writeSplitOrders($context);
        });

        $outcome = new CheckoutOutcome(
            $orders,
            $paymentMethod,
            $this->executePayments($orders, $strategy),
        );

        OrderPlaced::dispatch($orders, $context->cart->user, $outcome);

        return $outcome;
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

    /**
     * Run the resolved strategy once per split order, after the checkout
     * transaction has committed, and remember each issued reference.
     *
     * A multi-vendor cart shares one method across its orders, so every split
     * order is charged exactly once and gets its own reference. Results are
     * returned rather than thrown: a decline is a normal outcome here and the
     * orders are already committed.
     *
     * @param  Collection<int, Order>  $orders
     * @return Collection<int, PaymentResult> keyed by order id
     */
    private function executePayments(Collection $orders, ?PaymentStrategyInterface $strategy): Collection
    {
        $results = new Collection;

        if ($strategy === null) {
            return $results;
        }

        foreach ($orders as $order) {
            $result = $strategy->pay($order);

            if ($result->successful && $result->reference !== null) {
                $this->orders->recordPaymentReference($order, $result->reference);
            }

            $results->put((int) $order->id, $result);
        }

        return $results;
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
     * The map lives in config/marketplace.php (marketplace.orders.transitions) and
     * is enforced by OrderTransitionService, the single gate shared with the
     * payment strategies that settle an order during checkout.
     * Authorization (who may apply a transition) stays in the controller/policy
     * layer.
     *
     * @throws InvalidOrderTransitionException when the transition is not allowed
     */
    public function transitionTo(Order $order, OrderStatus $target): Order
    {
        return $this->transitions->apply($order, $target);
    }
}
