<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Events\OrderPaid;
use App\Events\OrderPlacing;
use App\Events\OrderShipped;
use App\Listeners\NotifyCustomerOnPayment;
use App\Listeners\NotifyCustomerOnShipment;
use App\Listeners\ReduceInventory;
use App\Listeners\SendOrderNotifications;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Day 20 — Observer Pattern: OrderPlacing reserves stock inside the
 * checkout transaction, while OrderPaid and OrderShipped are emitted by
 * the shared lifecycle gate for their queued notification listeners.
 */
class OrderObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_dispatches_order_placing_with_the_cart_lines(): void
    {
        Event::fake([OrderPlacing::class]);

        $customer = $this->customer();
        $product = $this->visibleProduct();
        $this->stockFor($product, 5);
        $this->addToCart($customer, $product, 2);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/checkout', ['payment_method' => 'cod'])
            ->assertCreated();

        Event::assertDispatched(OrderPlacing::class, function (OrderPlacing $event) use ($product): bool {
            return $event->lines->count() === 1
                && $event->lines->first()->quantity === 2
                && $event->lines->first()->product->is($product);
        });

        // Under the fake the ReduceInventory listener never ran — the shelf
        // proves this test pinned the dispatch itself, not its side effect.
        $this->assertDatabaseHas('inventories', ['product_id' => $product->id, 'quantity' => 5]);
    }

    public function test_the_stock_is_reduced_exactly_once_through_the_observer(): void
    {
        $customer = $this->customer();
        $product = $this->visibleProduct();
        $this->stockFor($product, 7);
        $this->addToCart($customer, $product, 3);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/checkout', ['payment_method' => 'card'])
            ->assertCreated();

        // 7 − 3 = 4: the synchronous listener replaced the inline loop and
        // runs exactly once — a double reduction would leave 1 behind.
        $this->assertDatabaseHas('inventories', ['product_id' => $product->id, 'quantity' => 4]);
    }

    public function test_order_paid_is_dispatched_when_a_card_payment_settles(): void
    {
        Event::fake([OrderPaid::class]);
        config()->set('marketplace.payments.card.simulate_success', true);

        $customer = $this->customer();
        $product = $this->visibleProduct();
        $this->stockFor($product, 5);
        $this->addToCart($customer, $product, 1);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/checkout', ['payment_method' => 'card'])
            ->assertCreated();

        Event::assertDispatched(OrderPaid::class, function (OrderPaid $event): bool {
            return $event->order->status === OrderStatus::PAID;
        });

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('orders', ['status' => 'paid']);
    }

    public function test_order_paid_is_dispatched_when_an_admin_marks_the_order_paid(): void
    {
        Event::fake([OrderPaid::class]);

        $admin = $this->admin();
        $customer = $this->customer();
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::PENDING,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'paid'])
            ->assertOk();

        // The gate announces the move whatever the actor: the admin here,
        // the payment strategy in the checkout case above.
        Event::assertDispatched(OrderPaid::class, fn (OrderPaid $event): bool => $event->order->is($order));

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
    }

    public function test_order_shipped_is_dispatched_when_the_vendor_ships(): void
    {
        Event::fake([OrderShipped::class]);

        $vendor = $this->vendor();
        $product = $this->productFor($vendor);
        $order = $this->orderWithLine($this->customer(), $product, [
            'order' => ['status' => OrderStatus::PAID],
        ]);

        $this->actingAs($vendor, 'sanctum')
            ->patchJson("/api/vendor/orders/{$order->id}/status", ['status' => 'shipped'])
            ->assertOk();

        Event::assertDispatched(OrderShipped::class, fn (OrderShipped $event): bool => $event->order->is($order));

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'shipped']);
    }

    public function test_the_customer_is_notified_once_when_the_order_is_paid_and_shipped(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::PENDING,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'paid'])
            ->assertOk();
        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'shipped'])
            ->assertOk();

        // Real listeners under the sync driver: one row per lifecycle event,
        // never duplicated — the queued job ran inline, the gate fired once.
        $this->assertSame(1, $customer->notifications()->where('type', 'customer_payment_received')->count());
        $this->assertSame(1, $customer->notifications()->where('type', 'customer_order_shipped')->count());
        $this->assertSame(2, $customer->notifications()->count());
    }

    public function test_the_inventory_listener_is_synchronous_and_the_notification_listeners_are_queued(): void
    {
        // The reservation must run inside the checkout transaction to keep
        // its rollback semantics; deliveries may wait for a worker (the PRD
        // asks for queued listeners — Day 25 supplies the worker).
        $this->assertNotInstanceOf(ShouldQueue::class, app(ReduceInventory::class));
        $this->assertInstanceOf(ShouldQueue::class, app(SendOrderNotifications::class));
        $this->assertInstanceOf(ShouldQueue::class, app(NotifyCustomerOnPayment::class));
        $this->assertInstanceOf(ShouldQueue::class, app(NotifyCustomerOnShipment::class));
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => UserRole::CUSTOMER]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::ADMIN]);
    }

    private function vendor(): User
    {
        return User::factory()->create(['role' => UserRole::VENDOR]);
    }

    private function visibleProduct(): Product
    {
        $store = Store::factory()->create(['status' => StoreStatus::APPROVED]);

        // Priced well inside the fraud ceiling (config: max_order_total),
        // mirroring PaymentMethodsTest so checkout reaches the observers.
        return Product::factory()->create(['store_id' => $store->id, 'base_price' => '25.00']);
    }

    private function productFor(User $vendor): Product
    {
        $profile = VendorProfile::factory()->create([
            'user_id' => $vendor->id,
            'verification_status' => StoreStatus::APPROVED,
        ]);
        $store = Store::factory()->create([
            'vendor_profile_id' => $profile->id,
            'status' => StoreStatus::APPROVED,
        ]);

        return Product::factory()->create(['store_id' => $store->id]);
    }

    /**
     * Create an order for the buyer containing one line for the given product.
     *
     * @param  array{order?: array<string, mixed>, line?: array<string, mixed>}  $options
     */
    private function orderWithLine(User $buyer, Product $product, array $options = []): Order
    {
        $order = Order::factory()->create(array_merge(
            ['user_id' => $buyer->id],
            $options['order'] ?? [],
        ));

        OrderItem::factory()->create(array_merge(
            ['order_id' => $order->id, 'product_id' => $product->id],
            $options['line'] ?? [],
        ));

        return $order;
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
}
