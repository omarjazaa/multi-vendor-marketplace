<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Enums\StoreStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Models\VendorProfile;
use App\Queries\Vendor\VendorBestSellersQuery;
use App\Queries\Vendor\VendorDashboardQuery;
use App\Queries\Vendor\VendorRevenueOverTimeQuery;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorAnalyticsQueriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_query_aggregates_metrics_for_store(): void
    {
        $store = $this->createStore();

        // Realized orders (contribute to revenue)
        $paidOrder = Order::factory()->create([
            'store_id' => $store->id,
            'status' => OrderStatus::PAID,
            'total_price' => 150.00,
        ]);
        OrderItem::factory()->create([
            'order_id' => $paidOrder->id,
            'quantity' => 2,
            'unit_price' => 75.00,
        ]);

        $deliveredOrder = Order::factory()->create([
            'store_id' => $store->id,
            'status' => OrderStatus::DELIVERED,
            'total_price' => 50.00,
        ]);
        OrderItem::factory()->create([
            'order_id' => $deliveredOrder->id,
            'quantity' => 1,
            'unit_price' => 50.00,
        ]);

        // Pending order (should NOT count toward revenue, but counted in pending_orders_count)
        Order::factory()->create([
            'store_id' => $store->id,
            'status' => OrderStatus::PENDING,
            'total_price' => 80.00,
        ]);

        // Cancelled order (should NOT count toward revenue)
        Order::factory()->create([
            'store_id' => $store->id,
            'status' => OrderStatus::CANCELLED,
            'total_price' => 200.00,
        ]);

        $query = new VendorDashboardQuery;
        $result = $query->execute($store->id);

        $this->assertSame(200.00, $result['total_revenue']);
        $this->assertSame(2, $result['total_orders']);
        $this->assertSame(3, $result['total_items_sold']);
        $this->assertSame(100.00, $result['average_order_value']);
        $this->assertSame(1, $result['pending_orders_count']);
        $this->assertSame(1, $result['delivered_orders_count']);
    }

    public function test_best_sellers_query_ranks_by_units_sold_and_revenue(): void
    {
        $store = $this->createStore();

        $productA = Product::factory()->create(['store_id' => $store->id, 'name' => 'Product Alpha']);
        $productB = Product::factory()->create(['store_id' => $store->id, 'name' => 'Product Beta']);
        $productC = Product::factory()->create(['store_id' => $store->id, 'name' => 'Product Gamma']);

        $order = Order::factory()->create([
            'store_id' => $store->id,
            'status' => OrderStatus::PAID,
        ]);

        // Product A: 10 units @ $10 = $100
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $productA->id,
            'name' => 'Product Alpha',
            'quantity' => 10,
            'unit_price' => 10.00,
        ]);

        // Product B: 25 units @ $5 = $125 (top seller by units)
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $productB->id,
            'name' => 'Product Beta',
            'quantity' => 25,
            'unit_price' => 5.00,
        ]);

        // Product C: 2 units @ $30 = $60
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $productC->id,
            'name' => 'Product Gamma',
            'quantity' => 2,
            'unit_price' => 30.00,
        ]);

        $query = new VendorBestSellersQuery;
        $results = $query->execute($store->id, 2);

        $this->assertCount(2, $results);
        $this->assertSame($productB->id, $results[0]['product_id']);
        $this->assertSame(25, $results[0]['units_sold']);
        $this->assertSame(125.00, $results[0]['total_revenue']);

        $this->assertSame($productA->id, $results[1]['product_id']);
        $this->assertSame(10, $results[1]['units_sold']);
        $this->assertSame(100.00, $results[1]['total_revenue']);
    }

    public function test_revenue_over_time_query_groups_by_date(): void
    {
        $store = $this->createStore();

        // 2 orders on day 1
        Order::factory()->create([
            'store_id' => $store->id,
            'status' => OrderStatus::PAID,
            'total_price' => 100.00,
            'created_at' => Carbon::parse('2026-10-10 10:00:00'),
        ]);
        Order::factory()->create([
            'store_id' => $store->id,
            'status' => OrderStatus::SHIPPED,
            'total_price' => 50.00,
            'created_at' => Carbon::parse('2026-10-10 16:00:00'),
        ]);

        // 1 order on day 2
        Order::factory()->create([
            'store_id' => $store->id,
            'status' => OrderStatus::DELIVERED,
            'total_price' => 75.00,
            'created_at' => Carbon::parse('2026-10-11 12:00:00'),
        ]);

        $query = new VendorRevenueOverTimeQuery;
        $results = $query->execute(
            $store->id,
            Carbon::parse('2026-10-09 00:00:00'),
            Carbon::parse('2026-10-12 23:59:59'),
        );

        $this->assertCount(2, $results);
        $this->assertSame('2026-10-10', $results[0]['date']);
        $this->assertSame(150.00, $results[0]['revenue']);
        $this->assertSame(2, $results[0]['orders_count']);

        $this->assertSame('2026-10-11', $results[1]['date']);
        $this->assertSame(75.00, $results[1]['revenue']);
        $this->assertSame(1, $results[1]['orders_count']);
    }

    private function createStore(): Store
    {
        $user = User::factory()->create();
        $profile = VendorProfile::factory()->create(['user_id' => $user->id]);

        return Store::factory()->create([
            'vendor_profile_id' => $profile->id,
            'status' => StoreStatus::APPROVED,
        ]);
    }
}
