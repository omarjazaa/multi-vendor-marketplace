<?php

namespace App\Repositories\Contracts;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;

interface OrderRepositoryInterface
{
    /**
     * Persist a new order together with its snapshot lines.
     *
     * @param  string  $discount  this order's share of the cart-wide discount (Day 16)
     * @param  string  $tax  this order's share of the cart-wide tax (Day 16)
     * @param  array<int, array<string, mixed>>  $items
     * @return Order with items loaded
     */
    public function create(
        int $userId,
        ?int $storeId,
        OrderStatus $status,
        ?string $paymentMethod,
        string $discount,
        string $tax,
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

    /** Store the reference a payment strategy issued for this order (Day 17). */
    public function recordPaymentReference(Order $order, string $reference): Order;

    /** Count the orders a customer placed since the given instant (fraud window). */
    public function countForUserSince(int $userId, CarbonInterface $since): int;
}
