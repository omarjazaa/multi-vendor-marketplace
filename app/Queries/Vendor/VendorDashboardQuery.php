<?php

namespace App\Queries\Vendor;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;

/**
 * Lightweight CQRS: Read-side query for vendor dashboard metrics (Day 23).
 *
 * Dedicated read query separate from write-side OrderService, calculating
 * aggregate statistics directly for performance.
 */
class VendorDashboardQuery
{
    /** Realized statuses that contribute to earned revenue and sales counts. */
    public const REVENUE_STATUSES = [
        OrderStatus::PAID->value,
        OrderStatus::SHIPPED->value,
        OrderStatus::DELIVERED->value,
    ];

    /**
     * Fetch key summary metrics for a vendor's store.
     *
     * @return array{
     *     total_revenue: float,
     *     total_orders: int,
     *     total_items_sold: int,
     *     average_order_value: float,
     *     pending_orders_count: int,
     *     delivered_orders_count: int,
     * }
     */
    public function execute(int $storeId): array
    {
        // Aggregate order counts and realized revenue
        $orderStats = Order::query()
            ->where('store_id', $storeId)
            ->whereIn('status', self::REVENUE_STATUSES)
            ->selectRaw('COALESCE(SUM(total_price), 0) as total_revenue, COUNT(*) as total_orders')
            ->first();

        $totalRevenue = $orderStats ? round((float) $orderStats->total_revenue, 2) : 0.00;
        $totalOrders = $orderStats ? (int) $orderStats->total_orders : 0;
        $averageOrderValue = $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0.00;

        // Total items sold across realized orders
        $totalItemsSold = (int) OrderItem::query()
            ->whereHas('order', function ($query) use ($storeId): void {
                $query->where('store_id', $storeId)
                    ->whereIn('status', self::REVENUE_STATUSES);
            })
            ->sum('quantity');

        // Status breakdowns
        $pendingCount = Order::query()
            ->where('store_id', $storeId)
            ->where('status', OrderStatus::PENDING)
            ->count();

        $deliveredCount = Order::query()
            ->where('store_id', $storeId)
            ->where('status', OrderStatus::DELIVERED)
            ->count();

        return [
            'total_revenue' => $totalRevenue,
            'total_orders' => $totalOrders,
            'total_items_sold' => $totalItemsSold,
            'average_order_value' => $averageOrderValue,
            'pending_orders_count' => $pendingCount,
            'delivered_orders_count' => $deliveredCount,
        ];
    }
}
