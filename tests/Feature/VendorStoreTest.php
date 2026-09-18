<?php

namespace Tests\Feature;

use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_can_apply_to_open_a_pending_store(): void
    {
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);

        $response = $this->actingAs($vendor, 'sanctum')->postJson('/api/vendor/store', [
            'name' => 'Ada Crafts',
            'slug' => 'ada-crafts',
            'description' => 'Handmade goods from Ada.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.store.name', 'Ada Crafts')
            ->assertJsonPath('data.store.status', StoreStatus::PENDING->value);

        $this->assertDatabaseHas('vendor_profiles', [
            'user_id' => $vendor->id,
            'verification_status' => StoreStatus::PENDING->value,
        ]);
        $this->assertDatabaseHas('stores', [
            'name' => 'Ada Crafts',
            'slug' => 'ada-crafts',
            'status' => StoreStatus::PENDING->value,
        ]);
    }

    public function test_vendor_cannot_submit_a_second_store_application(): void
    {
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);

        $payload = [
            'name' => 'First Store',
            'slug' => 'first-store',
        ];

        $this->actingAs($vendor, 'sanctum')
            ->postJson('/api/vendor/store', $payload)
            ->assertCreated();

        $this->actingAs($vendor, 'sanctum')
            ->postJson('/api/vendor/store', [
                'name' => 'Second Store',
                'slug' => 'second-store',
            ])
            ->assertStatus(409);

        $this->assertDatabaseCount('stores', 1);
    }

    public function test_only_vendors_can_apply_for_a_store(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $payload = ['name' => 'Not Allowed', 'slug' => 'not-allowed'];

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/vendor/store', $payload)
            ->assertForbidden();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/vendor/store', $payload)
            ->assertForbidden();
    }

    public function test_store_application_requires_authentication(): void
    {
        $this->postJson('/api/vendor/store', [
            'name' => 'Unauthenticated Store',
            'slug' => 'unauthenticated-store',
        ])->assertUnauthorized();
    }

    public function test_store_application_validates_unique_store_slugs(): void
    {
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        Store::factory()->create(['slug' => 'existing-store']);

        $this->actingAs($vendor, 'sanctum')
            ->postJson('/api/vendor/store', [
                'name' => 'Another Store',
                'slug' => 'existing-store',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    }
}
