<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Jobs\GenerateVendorReportJob;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Models\VendorProfile;
use App\Models\VendorReport;
use App\Queries\Vendor\VendorDashboardQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Background Jobs & Queues (Day 25 — PRD §5 Week 4).
 *
 * Verifies asynchronous analytics report generation through queued jobs,
 * secure storage artifacts, and vendor access control.
 */
class VendorReportGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_can_request_report_generation_dispatches_queued_job(): void
    {
        Queue::fake();

        [$vendor, $store] = $this->vendorWithApprovedStore();

        $response = $this->actingAs($vendor, 'sanctum')
            ->postJson('/api/vendor/analytics/reports', [
                'type' => 'sales_summary',
            ]);

        $response->assertStatus(202)
            ->assertJsonPath('data.report.status', ReportStatus::PENDING->value)
            ->assertJsonPath('data.report.type', ReportType::SALES_SUMMARY->value);

        $this->assertDatabaseHas('vendor_reports', [
            'store_id' => $store->id,
            'type' => ReportType::SALES_SUMMARY->value,
            'status' => ReportStatus::PENDING->value,
        ]);

        Queue::assertPushed(GenerateVendorReportJob::class, function (GenerateVendorReportJob $job) use ($store): bool {
            return $job->queue === 'reports'
                && $job->report->store_id === $store->id
                && $job->report->type === ReportType::SALES_SUMMARY;
        });
    }

    public function test_generate_vendor_report_job_executes_and_saves_report_file(): void
    {
        Storage::fake('local');

        [$vendor, $store] = $this->vendorWithApprovedStore();
        $this->seedSalesForStore($store);

        /** @var VendorReport $report */
        $report = VendorReport::factory()->create([
            'store_id' => $store->id,
            'type' => ReportType::SALES_SUMMARY,
            'status' => ReportStatus::PENDING,
        ]);

        $job = new GenerateVendorReportJob($report);
        app()->call([$job, 'handle']);

        $report->refresh();

        $this->assertSame(ReportStatus::COMPLETED, $report->status);
        $this->assertNotNull($report->file_path);
        $this->assertNotNull($report->completed_at);
        $this->assertNull($report->error_message);

        Storage::disk('local')->assertExists($report->file_path);

        $content = Storage::disk('local')->get($report->file_path);
        $decoded = json_decode($content, true);

        $this->assertSame('sales_summary', $decoded['report_type']);
        $this->assertSame($store->id, $decoded['store_id']);
        $this->assertArrayHasKey('summary', $decoded);
        $this->assertArrayHasKey('top_products', $decoded);
    }

    public function test_vendor_can_list_their_requested_reports(): void
    {
        [$vendor, $store] = $this->vendorWithApprovedStore();

        VendorReport::factory()->count(3)->create([
            'store_id' => $store->id,
        ]);

        $response = $this->actingAs($vendor, 'sanctum')
            ->getJson('/api/vendor/analytics/reports');

        $response->assertOk()
            ->assertJsonCount(3, 'data.reports.data');
    }

    public function test_vendor_can_view_a_specific_report(): void
    {
        [$vendor, $store] = $this->vendorWithApprovedStore();

        $report = VendorReport::factory()->completed()->create([
            'store_id' => $store->id,
        ]);

        $response = $this->actingAs($vendor, 'sanctum')
            ->getJson("/api/vendor/analytics/reports/{$report->id}");

        $response->assertOk()
            ->assertJsonPath('data.report.id', $report->id)
            ->assertJsonPath('data.report.status', ReportStatus::COMPLETED->value);
    }

    public function test_vendor_can_download_completed_report(): void
    {
        Storage::fake('local');

        [$vendor, $store] = $this->vendorWithApprovedStore();

        $filePath = "reports/store_{$store->id}/test_report.json";
        Storage::disk('local')->put($filePath, json_encode(['test' => 'data']));

        $report = VendorReport::factory()->create([
            'store_id' => $store->id,
            'status' => ReportStatus::COMPLETED,
            'file_path' => $filePath,
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($vendor, 'sanctum')
            ->get("/api/vendor/analytics/reports/{$report->id}/download");

        $response->assertOk();
    }

    public function test_downloading_pending_report_returns_conflict(): void
    {
        [$vendor, $store] = $this->vendorWithApprovedStore();

        $report = VendorReport::factory()->create([
            'store_id' => $store->id,
            'status' => ReportStatus::PENDING,
            'file_path' => null,
        ]);

        $response = $this->actingAs($vendor, 'sanctum')
            ->getJson("/api/vendor/analytics/reports/{$report->id}/download");

        $response->assertStatus(409)
            ->assertJsonPath('errors.status', 'pending');
    }

    public function test_vendor_cannot_view_or_download_another_stores_report(): void
    {
        [$vendor1, $store1] = $this->vendorWithApprovedStore();
        [$vendor2, $store2] = $this->vendorWithApprovedStore();

        $report2 = VendorReport::factory()->completed()->create([
            'store_id' => $store2->id,
        ]);

        $this->actingAs($vendor1, 'sanctum')
            ->getJson("/api/vendor/analytics/reports/{$report2->id}")
            ->assertStatus(403);

        $this->actingAs($vendor1, 'sanctum')
            ->getJson("/api/vendor/analytics/reports/{$report2->id}/download")
            ->assertStatus(403);
    }

    public function test_unapproved_vendor_or_customer_cannot_request_reports(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $unapprovedVendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $profile = VendorProfile::factory()->create([
            'user_id' => $unapprovedVendor->id,
            'verification_status' => StoreStatus::PENDING,
        ]);
        Store::factory()->create([
            'vendor_profile_id' => $profile->id,
            'status' => StoreStatus::PENDING,
        ]);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/vendor/analytics/reports', ['type' => 'sales_summary'])
            ->assertStatus(403);

        $this->actingAs($unapprovedVendor, 'sanctum')
            ->postJson('/api/vendor/analytics/reports', ['type' => 'sales_summary'])
            ->assertStatus(403);
    }

    public function test_job_failure_updates_report_status_to_failed(): void
    {
        [$vendor, $store] = $this->vendorWithApprovedStore();

        $report = VendorReport::factory()->create([
            'store_id' => $store->id,
            'type' => ReportType::SALES_SUMMARY,
            'status' => ReportStatus::PENDING,
        ]);

        // Mock dashboard query to throw exception
        $mockQuery = $this->createMock(VendorDashboardQuery::class);
        $mockQuery->method('execute')->willThrowException(new \RuntimeException('Database calculation error'));
        $this->app->instance(VendorDashboardQuery::class, $mockQuery);

        $job = new GenerateVendorReportJob($report);

        try {
            app()->call([$job, 'handle']);
            $this->fail('Expected exception was not thrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Database calculation error', $e->getMessage());
        }

        $report->refresh();
        $this->assertSame(ReportStatus::FAILED, $report->status);
        $this->assertSame('Database calculation error', $report->error_message);
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function vendorWithApprovedStore(): array
    {
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $profile = VendorProfile::factory()->create([
            'user_id' => $vendor->id,
            'verification_status' => StoreStatus::APPROVED,
        ]);
        $store = Store::factory()->create([
            'vendor_profile_id' => $profile->id,
            'status' => StoreStatus::APPROVED,
        ]);

        return [$vendor, $store];
    }

    private function seedSalesForStore(Store $store): void
    {
        $product = Product::factory()->create([
            'store_id' => $store->id,
            'base_price' => 50.00,
        ]);

        $order = Order::factory()->create([
            'store_id' => $store->id,
            'status' => OrderStatus::DELIVERED,
            'total_price' => 100.00,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 50.00,
        ]);

    }
}
