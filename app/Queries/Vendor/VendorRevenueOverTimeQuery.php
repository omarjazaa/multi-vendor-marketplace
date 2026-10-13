<?php

namespace App\Queries\Vendor;

use App\Enums\OrderStatus;
use App\Models\Order;
use Carbon\Carbon;

/**
 * Lightweight CQRS: Read-side query for historical revenue trend (Day 23).
 */
class VendorRevenueOverTimeQuery
{
    /** Realized statuses that contribute to earned revenue and sales counts. */
    public const REVENUE_STATUSES = [
        OrderStatus::PAID->value,
        OrderStatus::SHIPPED->value,
        OrderStatus::DELIVERED->value,
    ];

    /**
     * Group realized revenue and order counts by date over the requested period.
     *
     * @return array<int, array{
     *     date: string,
     *     revenue: float,
     *     orders_count: int,
     * }>
     */
    public function execute(int $storeId, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->subDays(30)->startOfDay();
        $endDate = $endDate ?? now()->endOfDay();

        $rows = Order::query()
            ->where('store_id', $storeId)
            ->whereIn('status', self::REVENUE_STATUSES)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as order_date, SUM(total_price) as daily_revenue, COUNT(*) as daily_orders')
            ->groupBy('order_date')
            ->orderBy('order_date', 'asc')
            ->get();

        return $rows->map(fn ($row): array => [
            'date' => (string) $row->order_date,
            'revenue' => round((float) $row->daily_revenue, 2),
            'orders_count' => (int) $row->daily_orders,
        ])->all();
    }
}
