<?php

namespace Tests\Feature;

use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Events\OrderPlaced;
use App\Mail\OrderNotificationMail;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Models\VendorProfile;
use App\Notifications\Channels\DatabaseNotificationChannel;
use App\Notifications\Channels\NotificationChannelFactory;
use App\Notifications\WelcomeVendorNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrderNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_dispatches_the_order_placed_event_with_payment_outcome(): void
    {
        Event::fake([OrderPlaced::class]);

        $customer = $this->customer();
        $product = $this->visibleProduct();
        $this->stockFor($product, 5);
        $this->addToCart($customer, $product, 2);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/checkout', ['payment_method' => 'cod'])
            ->assertCreated();

        Event::assertDispatched(OrderPlaced::class, function (OrderPlaced $event) use ($customer): bool {
            return $event->customer->is($customer)
                && $event->orders->count() === 1
                && $event->outcome->paymentMethod === 'cod'
                && $event->outcome->payments->first()->successful === true;
        });
    }

    public function test_a_declined_payment_still_notifies_with_the_decline(): void
    {
        $customer = $this->customer();
        $product = $this->visibleProduct();
        $this->stockFor($product, 5);
        $this->addToCart($customer, $product, 1);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/checkout', ['payment_method' => 'card'])
            ->assertCreated();

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('notifications', 2);

        $row = $customer->notifications()->where('type', 'customer_confirmation')->firstOrFail();
        $decline = (string) config('marketplace.payments.card.decline_message');
        $this->assertStringContainsString($decline, json_encode($row->data));
    }

    public function test_customer_gets_every_split_order_and_each_vendor_gets_only_their_own(): void
    {
        $customer = $this->customer();
        $vendor = $this->vendorUser();
        $rival = $this->vendorUser();
        $own = $this->productFor($vendor, ['base_price' => '10.00']);
        $theirs = $this->productFor($rival, ['base_price' => '20.00']);
        $this->stockFor($own, 5);
        $this->stockFor($theirs, 5);
        $this->addToCart($customer, $own, 1);
        $this->addToCart($customer, $theirs, 1);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/checkout', ['payment_method' => 'cod'])
            ->assertCreated();

        $orders = $response->json('data.orders');
        $this->assertCount(2, $orders);

        // Two vendor rows plus one customer row.
        $this->assertDatabaseCount('notifications', 3);

        $customerRow = $customer->notifications()->where('type', 'customer_confirmation')->firstOrFail();
        $customerOrders = $customerRow->data['data']['orders'];
        $this->assertCount(2, $customerOrders);
        $this->assertSame($orders[0]['total_price'], $customerOrders[0]['total_price']);
        $this->assertSame($orders[0]['discount'], $customerOrders[0]['discount']);

        $ownRow = $vendor->notifications()->where('type', 'vendor_new_order')->firstOrFail();
        $ownOrders = $ownRow->data['data']['orders'];
        $this->assertCount(1, $ownOrders);
        $this->assertSame($orders[0]['id'], $ownOrders[0]['order_id']);
        $this->assertSame($customer->name, $ownRow->data['data']['buyer']['name']);

        $rivalRow = $rival->notifications()->where('type', 'vendor_new_order')->firstOrFail();
        $rivalOrders = $rivalRow->data['data']['orders'];
        $this->assertCount(1, $rivalOrders);
        $this->assertSame($orders[1]['id'], $rivalOrders[0]['order_id']);
    }

    public function test_the_mail_channel_queues_an_order_email_when_selected(): void
    {
        Mail::fake();

        config()->set('marketplace.notifications.customer_channel', 'mail');
        config()->set('marketplace.notifications.vendor_channel', 'mail');
        config()->set('marketplace.notifications.queue_mail', true);

        $customer = $this->customer();
        $product = $this->visibleProduct();
        $this->stockFor($product, 5);
        $this->addToCart($customer, $product, 1);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/checkout', ['payment_method' => 'cod'])
            ->assertCreated();

        Mail::assertQueued(OrderNotificationMail::class, 2);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_a_failing_channel_does_not_undo_the_order(): void
    {
        config()->set('marketplace.notifications.channels.database', FailingChannel::class);

        $customer = $this->customer();
        $product = $this->visibleProduct();
        $this->stockFor($product, 5);
        $this->addToCart($customer, $product, 1);

        // The checkout still succeeds: delivery failures are reported, not thrown.
        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/checkout', ['payment_method' => 'cod'])
            ->assertCreated();

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_vendor_approval_still_sends_its_welcome_notification(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        [$vendor, $store] = $this->pendingApplication();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/stores/{$store->id}/approve")
            ->assertOk();

        Notification::assertSentTo($vendor, WelcomeVendorNotification::class);
    }

    public function test_the_supported_channels_stay_in_sync_with_the_sync_guard(): void
    {
        $this->assertSame(
            array_keys((array) config('marketplace.notifications.channels')),
            app(NotificationChannelFactory::class)->supported(),
        );

        $this->assertSame(
            DatabaseNotificationChannel::class,
            config('marketplace.notifications.channels.database'),
        );

    }

    private function customer(): User
    {
        return User::factory()->create(['role' => UserRole::CUSTOMER]);
    }

    private function vendorUser(): User
    {
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $profile = VendorProfile::factory()->create(['user_id' => $vendor->id]);
        Store::factory()->create(['vendor_profile_id' => $profile->id, 'status' => StoreStatus::APPROVED]);

        return $vendor;
    }

    private function visibleProduct(array $attributes = []): Product
    {
        $store = Store::factory()->create(['status' => StoreStatus::APPROVED]);

        return $this->productForStore($store, ['base_price' => '25.00'] + $attributes);
    }

    private function productFor(User $vendor, array $attributes = []): Product
    {
        return $this->productForStore($vendor->vendorProfile->store, $attributes);
    }

    private function productForStore(Store $store, array $attributes = []): Product
    {
        return Product::factory()->create(['store_id' => $store->id] + $attributes);
    }

    private function stockFor(Product $product, int $quantity): Inventory
    {
        return Inventory::factory()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'low_stock_threshold' => (int) config('marketplace.inventory.default_low_stock_threshold'),
        ]);
    }

    private function addToCart(User $customer, Product $product, int $quantity): void
    {
        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => $quantity])
            ->assertCreated();
    }

    /** @return array{0: User, 1: Store} */
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
