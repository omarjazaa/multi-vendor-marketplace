<?php

namespace App\Http\Controllers\Api;

use App\Enums\StoreStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\VendorRevenueRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Store;
use App\Queries\Vendor\VendorBestSellersQuery;
use App\Queries\Vendor\VendorDashboardQuery;
use App\Queries\Vendor\VendorRevenueOverTimeQuery;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vendor Analytics & Dashboard (Day 23).
 *
 * Exposes read-optimized analytics via lightweight CQRS query classes,
 * keeping reporting calculations decoupled from write-side command services.
 */
class VendorAnalyticsController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly VendorDashboardQuery $dashboardQuery,
        private readonly VendorBestSellersQuery $bestSellersQuery,
        private readonly VendorRevenueOverTimeQuery $revenueQuery,
    ) {}

    /** Comprehensive dashboard overview combining summary, top products, and recent trend. */
    public function dashboard(Request $request): JsonResponse
    {
        $store = $this->resolveVendorStore($request);
        if (! $store) {
            return $this->errorResponse('You must have an approved store to view analytics.', [], 403);
        }

        return $this->successResponse([
            'summary' => $this->dashboardQuery->execute($store->id),
            'best_sellers' => $this->bestSellersQuery->execute($store->id, 5),
            'revenue_trend' => $this->revenueQuery->execute(
                $store->id,
                now()->subDays(14)->startOfDay(),
                now()->endOfDay(),
            ),
        ]);
    }

    /** Key aggregate totals: revenue, orders, units sold, and average order value. */
    public function summary(Request $request): JsonResponse
    {
        $store = $this->resolveVendorStore($request);
        if (! $store) {
            return $this->errorResponse('You must have an approved store to view analytics.', [], 403);
        }

        return $this->successResponse([
            'summary' => $this->dashboardQuery->execute($store->id),
        ]);
    }

    /** Top performing products ranked by units sold and generated revenue. */
    public function bestSellers(Request $request): JsonResponse
    {
        $store = $this->resolveVendorStore($request);
        if (! $store) {
            return $this->errorResponse('You must have an approved store to view analytics.', [], 403);
        }

        $limit = (int) $request->query('limit', 5);

        return $this->successResponse([
            'best_sellers' => $this->bestSellersQuery->execute($store->id, $limit),
        ]);
    }

    /** Revenue grouped chronologically by date over a specified timeframe. */
    public function revenue(VendorRevenueRequest $request): JsonResponse
    {
        $store = $this->resolveVendorStore($request);
        if (! $store) {
            return $this->errorResponse('You must have an approved store to view analytics.', [], 403);
        }

        $validated = $request->validated();

        if (! empty($validated['from'])) {
            $startDate = Carbon::parse($validated['from'])->startOfDay();
            $endDate = ! empty($validated['to']) ? Carbon::parse($validated['to'])->endOfDay() : now()->endOfDay();
        } elseif (! empty($validated['days'])) {
            $startDate = now()->subDays((int) $validated['days'])->startOfDay();
            $endDate = now()->endOfDay();
        } else {
            $startDate = now()->subDays(30)->startOfDay();
            $endDate = now()->endOfDay();
        }

        return $this->successResponse([
            'period' => [
                'from' => $startDate->toDateString(),
                'to' => $endDate->toDateString(),
            ],
            'revenue_over_time' => $this->revenueQuery->execute($store->id, $startDate, $endDate),
        ]);
    }

    /** Resolve the authenticated vendor's approved store. */
    private function resolveVendorStore(Request $request): ?Store
    {
        $store = $request->user()?->vendorProfile?->store;

        if (! $store || $store->status !== StoreStatus::APPROVED) {
            return null;
        }

        return $store;
    }
}
