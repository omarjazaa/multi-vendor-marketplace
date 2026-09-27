<?php

namespace App\Repositories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentOrderRepository implements OrderRepositoryInterface
{
    /**
     * Persist a new order together with its snapshot lines.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return Order with items loaded
     */
    public function create(
        int $userId,
        OrderStatus $status,
        ?string $paymentMethod,
        string $totalPrice,
        array $items,
    ): Order {
        $order = Order::create([
            'user_id' => $userId,
            'status' => $status,
            'payment_method' => $paymentMethod,
            'total_price' => $totalPrice,
        ]);

        $order->items()->createMany($items);

        return $order->load('items');
    }

    /** Paginate a customer's own orders, newest first. */
    public function forUser(int $userId, int $perPage): LengthAwarePaginator
    {
        return Order::where('user_id', $userId)
            ->with('items')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }
}
