<?php

namespace Tests\Feature;

use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Listeners\NotifyCustomerOnPayment;
use App\Listeners\NotifyCustomerOnShipment;
use App\Listeners\SendOrderNotifications;
use App\Listeners\SendVendorWelcomeNotification;
use App\Models\Store;
use App\Models\User;
use App\Models\VendorProfile;
use App\Notifications\WelcomeVendorNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Background Jobs & Queues (Day 25 — PRD §5 Week 4).
 *
 * Verifies that all notifications and event listeners implement ShouldQueue
 * and are dispatched onto their dedicated background queues.
 */
class QueuedNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_vendor_welcome_notification_listener_is_queued(): void
    {
        $listener = app(SendVendorWelcomeNotification::class);

        $this->assertInstanceOf(ShouldQueue::class, $listener);
        $this->assertSame('notifications', $listener->queue);
    }

    public function test_welcome_vendor_notification_implements_should_queue(): void
    {
        $store = Store::factory()->create();
        $notification = new WelcomeVendorNotification($store);

        $this->assertInstanceOf(ShouldQueue::class, $notification);
        $this->assertSame('notifications', $notification->queue);
    }

    public function test_order_notification_listeners_implement_should_queue_on_notifications_queue(): void
    {
        $listeners = [
            app(SendOrderNotifications::class),
            app(NotifyCustomerOnPayment::class),
            app(NotifyCustomerOnShipment::class),
        ];

        foreach ($listeners as $listener) {
            $this->assertInstanceOf(ShouldQueue::class, $listener);
            $this->assertSame('notifications', $listener->queue);
        }
    }

    public function test_approving_store_dispatches_queued_vendor_welcome_notification(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $profile = VendorProfile::factory()->create([
            'user_id' => $vendor->id,
            'verification_status' => StoreStatus::PENDING,
        ]);
        $store = Store::factory()->create([
            'vendor_profile_id' => $profile->id,
            'status' => StoreStatus::PENDING,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/stores/{$store->id}/approve")
            ->assertOk();

        Notification::assertSentTo($vendor, WelcomeVendorNotification::class, function (WelcomeVendorNotification $notification) {
            return $notification->queue === 'notifications';
        });
    }
}
