<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\ReportType;
use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Jobs\GenerateVendorReportJob;
use App\Listeners\SendOrderNotifications;
use App\Listeners\SendVendorWelcomeNotification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Store;
use App\Models\User;
use App\Models\VendorProfile;
use App\Models\VendorReport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Day 28: Comprehensive Testing Sprint — Background Queues, CQRS Analytics & Rate Limiting Audit.
 *
 * Verifies explicit queue routing configurations for background workers, CQRS read-model
 * multi-vendor boundary isolation, and rate limiter throttling on high-load endpoints.
 */
class Day28QueuesAndAnalyticsAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('reports');
    }

    public function test_background_job_and_listeners_explicit_queue_routing(): void
    {
        // 1. GenerateVendorReportJob must target the 'reports' queue
        $store = Store::factory()->create();
        $report = VendorReport::create([
            'store_id' => $store->id,
            'type' => ReportType::SALES_SUMMARY,
            'status' => 'pending',
            'file_name' => 'sales-2026.csv',
        ]);
        $job = new GenerateVendorReportJob($report);

        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertSame('reports', $job->queue);

        // 2. Notification listeners must target the 'notifications' queue
        $welcomeListener = app(SendVendorWelcomeNotification::class);
        $this->assertInstanceOf(ShouldQueue::class, $welcomeListener);
        $this->assertSame('notifications', $welcomeListener->queue);

        $orderNotificationListener = app(SendOrderNotifications::class);
        $this->assertInstanceOf(ShouldQueue::class, $orderNotificationListener);
        $this->assertSame('notifications', $orderNotificationListener->queue);
    }

    public function test_cqrs_vendor_analytics_read_query_isolation_and_aggregations(): void
    {
        $vendorA = $this->vendorWithApprovedStore('Store Alpha');
        $storeA = $vendorA->vendorProfile->store;

        $vendorB = $this->vendorWithApprovedStore('Store Beta');
        $storeB = $vendorB->vendorProfile->store;

        // Paid order for Store A: 200.00
        $orderA = Order::factory()->create([
            'store_id' => $storeA->id,
            'status' => OrderStatus::PAID,
            'total_price' => 200.00,
            'created_at' => now(),
        ]);
        OrderItem::factory()->create([
            'order_id' => $orderA->id,
            'name' => 'Alpha Widget',
            'quantity' => 2,
            'unit_price' => 100.00,
        ]);

        // Paid order for Store B: 500.00
        $orderB = Order::factory()->create([
            'store_id' => $storeB->id,
            'status' => OrderStatus::PAID,
            'total_price' => 500.00,
            'created_at' => now(),
        ]);
        OrderItem::factory()->create([
            'order_id' => $orderB->id,
            'name' => 'Beta Machinery',
            'quantity' => 1,
            'unit_price' => 500.00,
        ]);

        // Vendor A analytics must reflect ONLY Store A's numbers
        $responseA = $this->actingAs($vendorA, 'sanctum')
            ->getJson('/api/v1/vendor/analytics/summary')
            ->assertOk();

        $responseA
            ->assertJsonPath('data.summary.total_revenue', 200)
            ->assertJsonPath('data.summary.total_orders', 1)
            ->assertJsonPath('data.summary.total_items_sold', 2);

        // Vendor B analytics must reflect ONLY Store B's numbers
        $responseB = $this->actingAs($vendorB, 'sanctum')
            ->getJson('/api/v1/vendor/analytics/summary')
            ->assertOk();

        $responseB
            ->assertJsonPath('data.summary.total_revenue', 500)
            ->assertJsonPath('data.summary.total_orders', 1)
            ->assertJsonPath('data.summary.total_items_sold', 1);
    }

    public function test_rate_limiting_enforcement_on_sensitive_reports_endpoint(): void
    {
        $vendor = $this->vendorWithApprovedStore('Fast Reports Store');

        // Rapid report requests: limit is 5 per minute
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($vendor, 'sanctum')
                ->postJson('/api/v1/vendor/analytics/reports', [
                    'type' => 'sales_summary',
                ])
                ->assertStatus(202);
        }

        // 6th attempt should be blocked by rate limiter with 429 Too Many Requests
        $response = $this->actingAs($vendor, 'sanctum')
            ->postJson('/api/v1/vendor/analytics/reports', [
                'type' => 'sales_summary',
            ])
            ->assertStatus(429);

        $response
            ->assertJsonPath('message', 'Too many report generation requests. Please wait a moment.')
            ->assertHeader('Retry-After');
    }

    private function vendorWithApprovedStore(string $storeName = 'Vendor Store'): User
    {
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $profile = VendorProfile::factory()->create(['user_id' => $vendor->id]);
        Store::factory()->create([
            'vendor_profile_id' => $profile->id,
            'name' => $storeName,
            'status' => StoreStatus::APPROVED,
        ]);

        return $vendor->load('vendorProfile.store');
    }
}
