<?php

namespace App\Repositories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
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
        ?int $storeId,
        OrderStatus $status,
        ?string $paymentMethod,
        string $totalPrice,
        array $items,
    ): Order {
        $order = Order::create([
            'user_id' => $userId,
            'store_id' => $storeId,
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

    /**
     * Paginate order lines belonging to a vendor's stores, newest first.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<OrderItem>
     */
    public function linesForVendor(int $userId, array $filters, int $perPage): LengthAwarePaginator
    {
        return OrderItem::query()
            ->whereHas('product.store.vendorProfile', fn ($query) => $query->where('user_id', $userId))
            ->with(['order.user'])
            ->when(
                $filters['status'] ?? null,
                fn ($query, $status) => $query->whereHas('order', fn ($order) => $order->where('status', $status)),
            )
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Paginate every order in the system for the admin listing, newest first.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<Order>
     */
    public function allForAdmin(array $filters, int $perPage): LengthAwarePaginator
    {
        return Order::query()
            ->with('items')
            ->when(
                $filters['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status),
            )
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /** Update an order's status. */
    public function updateStatus(Order $order, OrderStatus $status): Order
    {
        $order->update(['status' => $status]);

        return $order;
    }
}
