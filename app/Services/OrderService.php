<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\EmptyCartException;
use App\Exceptions\InsufficientStockException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Repositories\Contracts\CartRepositoryInterface;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly CartRepositoryInterface $carts,
        private readonly InventoryRepositoryInterface $inventories,
    ) {}

    /**
     * Convert a filled cart into an order inside a single transaction.
     *
     * Every line reserves its stock with an atomic guarded decrement before the
     * order is written; any failure rolls back the stock moves, the order and
     * the cart clearing together.
     *
     * @throws EmptyCartException when the cart holds no lines
     * @throws InsufficientStockException when a line outran available stock
     */
    public function place(Cart $cart, ?string $paymentMethod): Order
    {
        return DB::transaction(function () use ($cart, $paymentMethod): Order {
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

            $order = $this->orders->create(
                userId: $cart->user_id,
                status: OrderStatus::from((string) config('marketplace.checkout.default_status')),
                paymentMethod: $paymentMethod,
                totalPrice: number_format(
                    (float) $lines->sum(fn (CartItem $line): float => (float) $line->unit_price * $line->quantity),
                    2,
                    '.',
                    '',
                ),
                items: $lines->map(fn (CartItem $line): array => [
                    'product_id' => $line->product_id,
                    'name' => $line->product->name,
                    'unit_price' => $line->unit_price,
                    'quantity' => $line->quantity,
                ])->all(),
            );

            $this->carts->clear($cart);

            return $order;
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
}
