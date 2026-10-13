<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Models\VendorProfile;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_can_fetch_full_dashboard_overview(): void
    {
        $vendor = $this->vendorWithApprovedStore();
        $store = $vendor->vendorProfile->store;

        $order = Order::factory()->create([
            'store_id' => $store->id,
            'status' => OrderStatus::PAID,
            'total_price' => 120.00,
            'created_at' => now(),
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'name' => 'Premium Gadget',
            'quantity' => 2,
            'unit_price' => 60.00,
        ]);

        $response = $this->actingAs($vendor, 'sanctum')
            ->getJson('/api/vendor/analytics/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'total_revenue',
                        'total_orders',
                        'total_items_sold',
                        'average_order_value',
                        'pending_orders_count',
                        'delivered_orders_count',
                    ],
                    'best_sellers' => [
                        ['product_id', 'name', 'units_sold', 'total_revenue'],
                    ],
                    'revenue_trend',
                ],
                'message',
            ]);

        $response
            ->assertJsonPath('data.summary.total_revenue', 120)
            ->assertJsonPath('data.summary.total_orders', 1)
            ->assertJsonPath('data.summary.total_items_sold', 2)
            ->assertJsonPath('data.best_sellers.0.name', 'Premium Gadget');
    }

    public function test_vendor_can_fetch_summary_metrics(): void
    {
        $vendor = $this->vendorWithApprovedStore();
        $store = $vendor->vendorProfile->store;

        // Order 1: Paid ($100, 2 items)
        $order1 = Order::factory()->create([
            'store_id' => $store->id,
            'status' => OrderStatus::PAID,
            'total_price' => 100.00,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order1->id,
            'quantity' => 2,
            'unit_price' => 50.00,
        ]);

        // Order 2: Delivered ($200, 4 items)
        $order2 = Order::factory()->create([
            'store_id' => $store->id,
            'status' => OrderStatus::DELIVERED,
            'total_price' => 200.00,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order2->id,
            'quantity' => 4,
            'unit_price' => 50.00,
        ]);

        // Order 3: Pending ($50, should not count in revenue)
        Order::factory()->create([
            'store_id' => $store->id,
            'status' => OrderStatus::PENDING,
            'total_price' => 50.00,
        ]);

        $this->actingAs($vendor, 'sanctum')
            ->getJson('/api/vendor/analytics/summary')
            ->assertOk()
            ->assertJsonPath('data.summary.total_revenue', 300)
            ->assertJsonPath('data.summary.total_orders', 2)
            ->assertJsonPath('data.summary.total_items_sold', 6)
            ->assertJsonPath('data.summary.average_order_value', 150)
            ->assertJsonPath('data.summary.pending_orders_count', 1)
            ->assertJsonPath('data.summary.delivered_orders_count', 1);
    }

    public function test_vendor_can_fetch_best_sellers_with_custom_limit(): void
    {
        $vendor = $this->vendorWithApprovedStore();
        $store = $vendor->vendorProfile->store;

        $p1 = Product::factory()->create(['store_id' => $store->id, 'name' => 'Item One']);
        $p2 = Product::factory()->create(['store_id' => $store->id, 'name' => 'Item Two']);

        $order = Order::factory()->create(['store_id' => $store->id, 'status' => OrderStatus::PAID]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $p1->id,
            'name' => 'Item One',
            'quantity' => 10,
            'unit_price' => 15.00,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $p2->id,
            'name' => 'Item Two',
            'quantity' => 3,
            'unit_price' => 10.00,
        ]);

        $response = $this->actingAs($vendor, 'sanctum')
            ->getJson('/api/vendor/analytics/best-sellers?limit=1')
            ->assertOk();

        $response
            ->assertJsonCount(1, 'data.best_sellers')
            ->assertJsonPath('data.best_sellers.0.name', 'Item One')
            ->assertJsonPath('data.best_sellers.0.units_sold', 10);
    }

    public function test_vendor_can_filter_revenue_by_date_range(): void
    {
        $vendor = $this->vendorWithApprovedStore();
        $store = $vendor->vendorProfile->store;

        Order::factory()->create([
            'store_id' => $store->id,
            'status' => OrderStatus::PAID,
            'total_price' => 250.00,
            'created_at' => Carbon::parse('2026-10-05 14:00:00'),
        ]);

        $response = $this->actingAs($vendor, 'sanctum')
            ->getJson('/api/vendor/analytics/revenue?from=2026-10-01&to=2026-10-10')
            ->assertOk()
            ->assertJsonPath('data.period.from', '2026-10-01')
            ->assertJsonPath('data.period.to', '2026-10-10')
            ->assertJsonPath('data.revenue_over_time.0.date', '2026-10-05')
            ->assertJsonPath('data.revenue_over_time.0.revenue', 250)
            ->assertJsonPath('data.revenue_over_time.0.orders_count', 1);
    }

    public function test_multi_vendor_data_isolation(): void
    {
        $vendorA = $this->vendorWithApprovedStore();
        $storeA = $vendorA->vendorProfile->store;

        $vendorB = $this->vendorWithApprovedStore();
        $storeB = $vendorB->vendorProfile->store;

        // Vendor A order ($100)
        Order::factory()->create([
            'store_id' => $storeA->id,
            'status' => OrderStatus::PAID,
            'total_price' => 100.00,
        ]);

        // Vendor B order ($500)
        Order::factory()->create([
            'store_id' => $storeB->id,
            'status' => OrderStatus::PAID,
            'total_price' => 500.00,
        ]);

        // Vendor A's summary must show $100, not $600
        $this->actingAs($vendorA, 'sanctum')
            ->getJson('/api/vendor/analytics/summary')
            ->assertOk()
            ->assertJsonPath('data.summary.total_revenue', 100)
            ->assertJsonPath('data.summary.total_orders', 1);

        // Vendor B's summary must show $500, not $600
        $this->actingAs($vendorB, 'sanctum')
            ->getJson('/api/vendor/analytics/summary')
            ->assertOk()
            ->assertJsonPath('data.summary.total_revenue', 500)
            ->assertJsonPath('data.summary.total_orders', 1);
    }

    public function test_unauthenticated_guest_cannot_access_analytics(): void
    {
        $this->getJson('/api/vendor/analytics/dashboard')->assertUnauthorized();
    }

    public function test_customer_role_cannot_access_vendor_analytics(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/vendor/analytics/dashboard')
            ->assertForbidden();
    }

    public function test_vendor_without_approved_store_is_forbidden(): void
    {
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $profile = VendorProfile::factory()->create([
            'user_id' => $vendor->id,
            'verification_status' => StoreStatus::PENDING,
        ]);
        Store::factory()->create([
            'vendor_profile_id' => $profile->id,
            'status' => StoreStatus::PENDING,
        ]);

        $this->actingAs($vendor, 'sanctum')
            ->getJson('/api/vendor/analytics/dashboard')
            ->assertForbidden()
            ->assertJsonPath('message', 'You must have an approved store to view analytics.');
    }

    private function vendorWithApprovedStore(): User
    {
        $user = User::factory()->create(['role' => UserRole::VENDOR]);
        $profile = VendorProfile::factory()->create([
            'user_id' => $user->id,
            'verification_status' => StoreStatus::APPROVED,
        ]);
        Store::factory()->create([
            'vendor_profile_id' => $profile->id,
            'status' => StoreStatus::APPROVED,
        ]);

        return $user;
    }
}
