<?php

namespace Tests\Feature;

use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_notifications(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
        $this->getJson('/api/v1/notifications/unread-count')->assertUnauthorized();
        $this->patchJson('/api/v1/notifications/some-uuid/read')->assertUnauthorized();
        $this->postJson('/api/v1/notifications/read-all')->assertUnauthorized();
        $this->deleteJson('/api/v1/notifications/some-uuid')->assertUnauthorized();
    }

    public function test_user_can_list_notifications_with_standard_envelope(): void
    {
        $user = User::factory()->create(['role' => UserRole::CUSTOMER]);

        $this->createNotification($user, 'Order Placed', 'Your order #101 was placed.');
        $this->createNotification($user, 'Order Shipped', 'Your order #101 has shipped.');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'notifications' => [
                        'data' => [
                            '*' => [
                                'id',
                                'type',
                                'title',
                                'body',
                                'data',
                                'is_read',
                                'read_at',
                                'created_at',
                                'created_at_human',
                            ],
                        ],
                        'links',
                        'meta' => [
                            'current_page',
                            'total',
                        ],
                    ],
                ],
                'message',
            ]);

        $this->assertCount(2, $response->json('data.notifications.data'));
        $this->assertSame(2, $response->json('data.notifications.meta.total'));
    }

    public function test_user_can_filter_unread_notifications(): void
    {
        $user = User::factory()->create(['role' => UserRole::CUSTOMER]);

        $unread = $this->createNotification($user, 'Unread One', 'Body 1');
        $read = $this->createNotification($user, 'Read One', 'Body 2', ['read_at' => now()]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/notifications?unread=1')
            ->assertOk();

        $items = $response->json('data.notifications.data');
        $this->assertCount(1, $items);
        $this->assertSame((string) $unread->id, $items[0]['id']);
        $this->assertFalse($items[0]['is_read']);

        $readResponse = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/notifications?unread=0')
            ->assertOk();

        $readItems = $readResponse->json('data.notifications.data');
        $this->assertCount(1, $readItems);
        $this->assertSame((string) $read->id, $readItems[0]['id']);
        $this->assertTrue($readItems[0]['is_read']);
    }

    public function test_user_can_get_accurate_unread_count(): void
    {
        $user = User::factory()->create(['role' => UserRole::CUSTOMER]);

        $this->createNotification($user, 'Unread 1', 'Content');
        $this->createNotification($user, 'Unread 2', 'Content');
        $this->createNotification($user, 'Read 1', 'Content', ['read_at' => now()]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJson([
                'data' => [
                    'unread_count' => 2,
                ],
            ]);
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $user = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $notification = $this->createNotification($user, 'Unread Alert', 'Needs attention.');

        $this->assertNull($notification->read_at);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJson([
                'data' => [
                    'notification' => [
                        'id' => (string) $notification->id,
                        'is_read' => true,
                    ],
                ],
                'message' => 'Notification marked as read.',
            ]);

        $this->assertNotNull($response->json('data.notification.read_at'));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_marking_already_read_notification_remains_successful(): void
    {
        $user = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $time = now()->subHours(2);
        $notification = $this->createNotification($user, 'Already Read', 'Old news.', ['read_at' => $time]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJson([
                'data' => [
                    'notification' => [
                        'id' => (string) $notification->id,
                        'is_read' => true,
                    ],
                ],
            ]);
    }

    public function test_user_cannot_access_or_mutate_another_users_notification(): void
    {
        $owner = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $stranger = User::factory()->create(['role' => UserRole::CUSTOMER]);

        $notification = $this->createNotification($owner, 'Confidential', 'Private details');

        // Stranger attempting to mark as read returns 404
        $this->actingAs($stranger, 'sanctum')
            ->patchJson("/api/v1/notifications/{$notification->id}/read")
            ->assertNotFound();

        // Stranger attempting to delete returns 404
        $this->actingAs($stranger, 'sanctum')
            ->deleteJson("/api/v1/notifications/{$notification->id}")
            ->assertNotFound();

        // Ensure owner's notification was untouched
        $this->assertDatabaseHas('notifications', ['id' => $notification->id]);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create(['role' => UserRole::CUSTOMER]);

        $this->createNotification($user, 'Note 1', 'Desc 1');
        $this->createNotification($user, 'Note 2', 'Desc 2');
        $this->createNotification($user, 'Note 3', 'Desc 3');

        $this->assertSame(3, $user->unreadNotifications()->count());

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJson([
                'data' => [
                    'marked_count' => 3,
                ],
                'message' => 'All notifications marked as read.',
            ]);

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }

    public function test_user_can_delete_notification(): void
    {
        $user = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $notification = $this->createNotification($user, 'To be deleted', 'Temporary alert');

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/notifications/{$notification->id}")
            ->assertOk()
            ->assertJson([
                'message' => 'Notification dismissed successfully.',
            ]);

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_notifications_are_accessible_via_unversioned_fallback(): void
    {
        $user = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $this->createNotification($user, 'Legacy Route Test', 'Body');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonStructure(['data' => ['notifications']]);
    }

    public function test_real_checkout_flow_populates_notifications_for_customer_and_vendor(): void
    {
        // 1. Create vendor with approved store
        $vendorUser = User::factory()->create(['role' => UserRole::VENDOR]);
        $profile = VendorProfile::factory()->create([
            'user_id' => $vendorUser->id,
            'verification_status' => StoreStatus::APPROVED,
        ]);
        $store = Store::factory()->create([
            'vendor_profile_id' => $profile->id,
            'status' => StoreStatus::APPROVED,
        ]);

        // 2. Create product & inventory
        $product = Product::factory()->create([
            'store_id' => $store->id,
            'is_active' => true,
            'base_price' => 50.00,
        ]);
        Inventory::factory()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'low_stock_threshold' => 2,
        ]);

        // 3. Customer places order
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertCreated();

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/checkout', [
                'payment_method' => 'card',
            ])
            ->assertCreated();

        // 4. Assert Customer has notification and can query it
        $customerNotifRes = $this->actingAs($customer, 'sanctum')
            ->getJson('/api/v1/notifications')
            ->assertOk();

        $customerItems = $customerNotifRes->json('data.notifications.data');
        $this->assertNotEmpty($customerItems);
        $customerNotifId = $customerItems[0]['id'];

        // Customer marks their notification as read
        $this->actingAs($customer, 'sanctum')
            ->patchJson("/api/v1/notifications/{$customerNotifId}/read")
            ->assertOk()
            ->assertJsonPath('data.notification.is_read', true);

        // 5. Assert Vendor has notification and can query it
        $vendorNotifRes = $this->actingAs($vendorUser, 'sanctum')
            ->getJson('/api/v1/notifications')
            ->assertOk();

        $vendorItems = $vendorNotifRes->json('data.notifications.data');
        $this->assertNotEmpty($vendorItems);
    }

    /**
     * Helper to create a database notification for a given user.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createNotification(User $user, string $title, string $body, array $overrides = [])
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\GenericNotification',
            'data' => [
                'kind' => 'order_alert',
                'title' => $title,
                'body' => $body,
                'context' => ['source' => 'test'],
            ],
            'read_at' => $overrides['read_at'] ?? null,
            'created_at' => $overrides['created_at'] ?? now(),
            'updated_at' => $overrides['updated_at'] ?? now(),
        ]);
    }
}
