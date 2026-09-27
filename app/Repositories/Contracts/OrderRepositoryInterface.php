<?php

namespace App\Repositories\Contracts;

use App\Enums\OrderStatus;
use App\Models\Order;
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
        OrderStatus $status,
        ?string $paymentMethod,
        string $totalPrice,
        array $items,
    ): Order;

    /** Paginate a customer's own orders, newest first. */
    public function forUser(int $userId, int $perPage): LengthAwarePaginator;
}
