<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\EmptyCartException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Repositories\Contracts\CartRepositoryInterface;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly CartRepositoryInterface $carts,
        private readonly InventoryRepositoryInterface $inventories,
    ) {}

    /**
     * Convert a filled cart into per-vendor orders inside a single transaction
     * (Day 15 multi-vendor checkout): one order per store, each carrying only
     * that vendor's lines and total.
     *
     * Every line reserves its stock with an atomic guarded decrement before any
     * order is written; a single failure rolls back the stock moves, all split
     * orders and the cart clearing together — it is all-or-nothing.
     *
     * @return Collection<int, Order> orders grouped by vendor, in first-seen store order
     *
     * @throws EmptyCartException when the cart holds no lines
     * @throws InsufficientStockException when a line outran available stock
     */
    public function place(Cart $cart, ?string $paymentMethod): Collection
    {
        return DB::transaction(function () use ($cart, $paymentMethod): Collection {
            $lines = $cart->items()->with('product')->get();

            if ($lines->isEmpty()) {
                throw new EmptyCartException;
            }

            foreach ($lines as $line) {
                if (! $this->inventories->decrementQuantity($line->product, $line->quantity)) {
                    $available = $this->inventories->firstOrCreateForProduct($line->product)->quantity;

                    throw new InsufficientStockException($available, $line->product->name);
                }
            }

            $status = OrderStatus::from((string) config('marketplace.checkout.default_status'));

            $orders = $lines
                ->groupBy(fn (CartItem $line): int => (int) $line->product->store_id)
                ->map(fn ($vendorLines, int $storeId): Order => $this->orders->create(
                    userId: $cart->user_id,
                    storeId: $storeId,
                    status: $status,
                    paymentMethod: $paymentMethod,
                    totalPrice: number_format(
                        (float) $vendorLines->sum(
                            fn (CartItem $line): float => (float) $line->unit_price * $line->quantity,
                        ),
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

            $this->carts->clear($cart);

            return $orders;
        });
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
