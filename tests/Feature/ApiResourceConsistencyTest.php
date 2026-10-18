<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\StoreResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\VendorReportResource;
use App\Models\Category;
use App\Models\Store;
use App\Models\User;
use App\Models\VendorReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Consistent API Resources Contract Tests (Day 26 — PRD §5 Week 4).
 *
 * Verifies that UserResource, StoreResource, CategoryResource, and VendorReportResource
 * transform models into consistent, predictable JSON contracts.
 */
class ApiResourceConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_resource_outputs_expected_contract(): void
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'role' => UserRole::CUSTOMER,
        ]);

        $resource = (new UserResource($user))->resolve();

        $this->assertSame($user->id, $resource['id']);
        $this->assertSame('John Doe', $resource['name']);
        $this->assertSame('john@example.com', $resource['email']);
        $this->assertSame(UserRole::CUSTOMER->value, $resource['role']);
        $this->assertArrayHasKey('created_at', $resource);
        $this->assertArrayHasKey('updated_at', $resource);
    }

    public function test_store_resource_outputs_expected_contract(): void
    {
        $store = Store::factory()->create([
            'name' => 'Artisan Coffee',
            'slug' => 'artisan-coffee',
            'description' => 'Specialty coffee roasters.',
            'status' => StoreStatus::APPROVED,
        ]);

        $resource = (new StoreResource($store))->resolve();

        $this->assertSame($store->id, $resource['id']);
        $this->assertSame('Artisan Coffee', $resource['name']);
        $this->assertSame('artisan-coffee', $resource['slug']);
        $this->assertSame('Specialty coffee roasters.', $resource['description']);
        $this->assertSame(StoreStatus::APPROVED->value, $resource['status']);
        $this->assertArrayHasKey('created_at', $resource);
        $this->assertArrayHasKey('updated_at', $resource);
    }

    public function test_category_resource_handles_hierarchical_children(): void
    {
        $parent = Category::factory()->create(['name' => 'Apparel', 'slug' => 'apparel']);
        $child = Category::factory()->create(['parent_id' => $parent->id, 'name' => 'Footwear', 'slug' => 'footwear']);

        $parent->load('children');

        $resource = (new CategoryResource($parent))->resolve();

        $this->assertSame($parent->id, $resource['id']);
        $this->assertSame('Apparel', $resource['name']);
        $this->assertNull($resource['parent_id']);
        $this->assertCount(1, $resource['children']);
        $this->assertSame('Footwear', $resource['children'][0]['name']);
    }

    public function test_vendor_report_resource_outputs_expected_contract(): void
    {
        $store = Store::factory()->create();
        $report = VendorReport::factory()->completed()->create([
            'store_id' => $store->id,
            'type' => ReportType::SALES_SUMMARY,
            'status' => ReportStatus::COMPLETED,
            'parameters' => ['from' => '2026-01-01', 'to' => '2026-01-31'],
        ]);

        $resource = (new VendorReportResource($report))->resolve();

        $this->assertSame($report->id, $resource['id']);
        $this->assertSame($store->id, $resource['store_id']);
        $this->assertSame(ReportType::SALES_SUMMARY->value, $resource['type']);
        $this->assertSame(ReportStatus::COMPLETED->value, $resource['status']);
        $this->assertSame(['from' => '2026-01-01', 'to' => '2026-01-31'], $resource['parameters']);
        $this->assertNotNull($resource['completed_at']);
        $this->assertArrayHasKey('created_at', $resource);
    }

    public function test_endpoints_return_consistent_resource_envelopes(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $store = Store::factory()->create(['status' => StoreStatus::PENDING]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/stores/{$store->id}/approve");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'store' => [
                        'id',
                        'name',
                        'slug',
                        'status',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'message',
            ])
            ->assertJsonPath('data.store.status', StoreStatus::APPROVED->value);
    }
}
