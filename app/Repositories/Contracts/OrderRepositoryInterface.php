<?php

namespace App\Repositories\Contracts;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Pagination\LengthAwarePaginator;

interface OrderRepositoryInterface
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
    ): Order;

    /** Paginate a customer's own orders, newest first. */
    public function forUser(int $userId, int $perPage): LengthAwarePaginator;

    /**
     * Paginate order lines belonging to a vendor's stores.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<OrderItem>
     */
    public function linesForVendor(int $userId, array $filters, int $perPage): LengthAwarePaginator;

    /**
     * Paginate all orders in the system for admin view.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<Order>
     */
    public function allForAdmin(array $filters, int $perPage): LengthAwarePaginator;

    /** Update an order's status. */
    public function updateStatus(Order $order, OrderStatus $status): Order;
}
