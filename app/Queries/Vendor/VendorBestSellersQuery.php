<?php

namespace App\Queries\Vendor;

use App\Enums\OrderStatus;
use App\Models\OrderItem;

/**
 * Lightweight CQRS: Read-side query for top performing products (Day 23).
 */
class VendorBestSellersQuery
{
    /** Realized statuses that contribute to earned revenue and sales counts. */
    public const REVENUE_STATUSES = [
        OrderStatus::PAID->value,
        OrderStatus::SHIPPED->value,
        OrderStatus::DELIVERED->value,
    ];

    /**
     * Retrieve the best selling products for the given store.
     *
     * @return array<int, array{
     *     product_id: int,
     *     name: string,
     *     units_sold: int,
     *     total_revenue: float,
     * }>
     */
    public function execute(int $storeId, int $limit = 5): array
    {
        $limit = max(1, min($limit, 50));

        $results = OrderItem::query()
            ->whereHas('order', function ($query) use ($storeId): void {
                $query->where('store_id', $storeId)
                    ->whereIn('status', self::REVENUE_STATUSES);
            })
            ->selectRaw('product_id, name, SUM(quantity) as units_sold, SUM(quantity * unit_price) as total_revenue')
            ->groupBy('product_id', 'name')
            ->orderByDesc('units_sold')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get();

        return $results->map(fn ($row): array => [
            'product_id' => (int) $row->product_id,
            'name' => (string) $row->name,
            'units_sold' => (int) $row->units_sold,
            'total_revenue' => round((float) $row->total_revenue, 2),
        ])->all();
    }
}
