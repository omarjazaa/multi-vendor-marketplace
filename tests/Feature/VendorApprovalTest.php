<?php

namespace Tests\Feature;

use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Events\VendorApproved;
use App\Models\Store;
use App\Models\User;
use App\Models\VendorProfile;
use App\Notifications\WelcomeVendorNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class VendorApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_approve_a_pending_store_application(): void
    {
        Notification::fake();
        Event::fake([VendorApproved::class]);

        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        [$vendor, $store] = $this->pendingApplication();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/stores/{$store->id}/approve");

        $response
            ->assertOk()
            ->assertJsonPath('data.store.status', StoreStatus::APPROVED->value);

        $this->assertDatabaseHas('stores', [
            'id' => $store->id,
            'status' => StoreStatus::APPROVED->value,
        ]);
        $this->assertDatabaseHas('vendor_profiles', [
            'id' => $vendor->vendorProfile->id,
            'verification_status' => StoreStatus::APPROVED->value,
        ]);
        $this->assertNotNull($vendor->vendorProfile->fresh()->verified_at);
        Event::assertDispatched(VendorApproved::class);

        Notification::assertNothingSent();
    }

    public function test_approval_event_listener_sends_a_welcome_notification(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        [$vendor, $store] = $this->pendingApplication();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/stores/{$store->id}/approve")
            ->assertOk();

        Notification::assertSentTo($vendor, WelcomeVendorNotification::class);
    }

    public function test_admin_can_reject_a_pending_store_application(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        [$vendor, $store] = $this->pendingApplication();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/stores/{$store->id}/reject")
            ->assertOk()
            ->assertJsonPath('data.store.status', StoreStatus::REJECTED->value);

        $this->assertDatabaseHas('stores', [
            'id' => $store->id,
            'status' => StoreStatus::REJECTED->value,
        ]);
        $this->assertDatabaseHas('vendor_profiles', [
            'id' => $vendor->vendorProfile->id,
            'verification_status' => StoreStatus::REJECTED->value,
        ]);
    }

    public function test_only_admins_can_review_store_applications(): void
    {
        $vendorUser = User::factory()->create(['role' => UserRole::VENDOR]);
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        [, $store] = $this->pendingApplication();

        $this->actingAs($vendorUser, 'sanctum')
            ->postJson("/api/admin/stores/{$store->id}/approve")
            ->assertForbidden();

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/admin/stores/{$store->id}/reject")
            ->assertForbidden();
    }

    public function test_non_pending_applications_cannot_be_reviewed_again(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        [, $store] = $this->pendingApplication();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/stores/{$store->id}/approve")
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/stores/{$store->id}/reject")
            ->assertStatus(409);
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function pendingApplication(): array
    {
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $profile = VendorProfile::factory()->create([
            'user_id' => $vendor->id,
            'verification_status' => StoreStatus::PENDING,
        ]);
        $store = Store::factory()->create([
            'vendor_profile_id' => $profile->id,
            'status' => StoreStatus::PENDING,
        ]);

        return [$vendor, $store];
    }
}
