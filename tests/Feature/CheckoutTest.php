<?php

namespace Tests\Feature;

use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_checkout_a_filled_cart(): void
    {
        $customer = $this->customer();
        $lamp = $this->visibleProduct(['name' => 'Desk Lamp', 'base_price' => '49.99']);
        $mug = $this->visibleProduct(['name' => 'Trail Mug', 'base_price' => '10.00']);
        $this->stockFor($lamp, 10);
        $this->stockFor($mug, 5);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $lamp->id, 'quantity' => 2])
            ->assertCreated();
        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $mug->id, 'quantity' => 3])
            ->assertCreated();

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/checkout', ['payment_method' => 'card'])
            ->assertCreated();

        $response->assertJsonStructure([
            'data' => [
                'order' => [
                    'id', 'user_id', 'status', 'payment_method', 'total_price',
                    'items', 'created_at', 'updated_at',
                ],
            ],
            'message',
        ]);

        $response
            ->assertJsonPath('message', 'Order placed.')
            ->assertJsonPath('data.order.user_id', $customer->id)
            ->assertJsonPath('data.order.status', 'pending')
            ->assertJsonPath('data.order.payment_method', 'card')
            ->assertJsonPath('data.order.total_price', '129.98') // 2 × 49.99 + 3 × 10.00
            ->assertJsonCount(2, 'data.order.items')
            ->assertJsonPath('data.order.items.0.name', 'Desk Lamp')
            ->assertJsonPath('data.order.items.0.unit_price', '49.99')
            ->assertJsonPath('data.order.items.0.quantity', 2);

        $orderId = $response->json('data.order.id');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 2);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'pending']);
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertDatabaseHas('inventories', ['product_id' => $lamp->id, 'quantity' => 8]);
        $this->assertDatabaseHas('inventories', ['product_id' => $mug->id, 'quantity' => 2]);
    }

    public function test_checkout_rejects_an_empty_cart(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/checkout')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Your cart is empty.')
            ->assertJsonStructure(['message', 'errors' => ['cart']]);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_conflicts_when_stock_dropped_after_adding(): void
    {
        $customer = $this->customer();
        $steady = $this->visibleProduct(['name' => 'Steady Lamp']);
        $scarce = $this->visibleProduct(['name' => 'Scarce Ring']);
        $this->stockFor($steady, 10);
        $this->stockFor($scarce, 5);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $steady->id, 'quantity' => 1])
            ->assertCreated();
        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $scarce->id, 'quantity' => 2])
            ->assertCreated();

        // A vendor restocks downward between add-to-cart and checkout.
        Inventory::query()->where('product_id', $scarce->id)->update(['quantity' => 1]);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/checkout')
            ->assertStatus(409)
            ->assertJsonPath('message', 'Insufficient stock during checkout.')
            ->assertJsonPath('errors.quantity', 'Only 1 units are available for Scarce Ring.');

        // The whole transaction rolled back: no order, stock restored, cart intact.
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseHas('inventories', ['product_id' => $steady->id, 'quantity' => 10]);
        $this->assertDatabaseHas('inventories', ['product_id' => $scarce->id, 'quantity' => 1]);
        $this->assertDatabaseCount('cart_items', 2);
    }

    public function test_checkout_drains_stock_to_exactly_zero_never_negative(): void
    {
        $first = $this->customer();
        $second = $this->customer();
        $product = $this->visibleProduct(['name' => 'Rare Gem', 'base_price' => '20.00']);
        $this->stockFor($product, 3);

        // Both carts pass the add-to-cart guard while three units remain.
        $this->actingAs($first, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2])
            ->assertCreated();
        $this->actingAs($second, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2])
            ->assertCreated();

        $this->actingAs($first, 'sanctum')
            ->postJson('/api/checkout')
            ->assertCreated()
            ->assertJsonPath('data.order.total_price', '40.00');

        $this->assertDatabaseHas('inventories', ['product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($second, 'sanctum')
            ->postJson('/api/checkout')
            ->assertStatus(409)
            ->assertJsonPath('errors.quantity', 'Only 1 units are available for Rare Gem.');

        // The guarded decrement never let the level dip below zero.
        $this->assertDatabaseHas('inventories', ['product_id' => $product->id, 'quantity' => 1]);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_order_prices_are_snapshotted_from_cart_lines(): void
    {
        $customer = $this->customer();
        $product = $this->visibleProduct(['base_price' => '49.99']);
        $this->stockFor($product, 5);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])
            ->assertCreated();

        $product->update(['base_price' => '99.99']);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/checkout')
            ->assertCreated();

        $response
            ->assertJsonPath('data.order.total_price', '49.99')
            ->assertJsonPath('data.order.items.0.unit_price', '49.99');

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'unit_price' => '49.99',
            'quantity' => 1,
        ]);
    }

    public function test_customers_can_list_their_own_orders(): void
    {
        $customer = $this->customer();
        $other = $this->customer();
        $mine = Order::factory()->count(3)->create(['user_id' => $customer->id]);
        Order::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($customer, 'sanctum')
            ->getJson('/api/orders')
            ->assertOk();

        $response->assertJsonStructure([
            'data' => [
                'orders' => [
                    'data' => [['id', 'user_id', 'status', 'payment_method', 'total_price', 'items']],
                    'links' => ['first', 'last', 'prev', 'next'],
                    'meta' => ['current_page', 'from', 'last_page', 'path', 'per_page', 'to', 'total'],
                ],
            ],
            'message',
        ]);

        $response
            ->assertJsonCount(3, 'data.orders.data')
            ->assertJsonPath('data.orders.meta.total', 3)
            ->assertJsonPath('data.orders.meta.current_page', 1)
            ->assertJsonPath('data.orders.meta.per_page', (int) config('marketplace.checkout.orders_per_page'));

        $this->assertEqualsCanonicalizing(
            $mine->pluck('id')->all(),
            array_column($response->json('data.orders.data'), 'id'),
        );
    }

    public function test_customers_can_view_a_single_order_with_items(): void
    {
        $customer = $this->customer();
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'payment_method' => 'cod',
            'total_price' => '59.97',
        ]);
        $order->items()->createMany([
            ['product_id' => Product::factory()->create()->id, 'name' => 'Desk Lamp', 'unit_price' => '19.99', 'quantity' => 3],
        ]);

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Success.')
            ->assertJsonPath('data.order.id', $order->id)
            ->assertJsonPath('data.order.status', 'pending')
            ->assertJsonPath('data.order.payment_method', 'cod')
            ->assertJsonPath('data.order.total_price', '59.97')
            ->assertJsonCount(1, 'data.order.items')
            ->assertJsonPath('data.order.items.0.name', 'Desk Lamp')
            ->assertJsonPath('data.order.items.0.unit_price', '19.99')
            ->assertJsonPath('data.order.items.0.quantity', 3)
            ->assertJsonStructure([
                'data' => [
                    'order' => [
                        'id', 'user_id', 'status', 'payment_method', 'total_price',
                        'items', 'created_at', 'updated_at',
                    ],
                ],
                'message',
            ]);
    }

    public function test_order_views_are_isolated_between_users(): void
    {
        $owner = $this->customer();
        $intruder = $this->customer();
        $order = Order::factory()->create(['user_id' => $owner->id]);

        // Guest checks first: actingAs below would otherwise persist.
        $this->getJson("/api/orders/{$order->id}")->assertUnauthorized();
        $this->getJson('/api/orders/999999')->assertUnauthorized();

        $this->actingAs($intruder, 'sanctum')
            ->getJson("/api/orders/{$order->id}")
            ->assertForbidden();
    }

    public function test_admin_can_view_any_order(): void
    {
        $customer = $this->customer();
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $order = Order::factory()->create(['user_id' => $customer->id]);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.order.id', $order->id);

        // Order history is auth-only, so admins list their (empty) history too.
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/orders')
            ->assertOk()
            ->assertJsonPath('data.orders.meta.total', 0);
    }

    public function test_checkout_requires_authentication_and_the_customer_role(): void
    {
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $product = $this->visibleProduct();
        $this->stockFor($product, 5);

        $this->postJson('/api/checkout')->assertUnauthorized();
        $this->getJson('/api/orders')->assertUnauthorized();
        $this->getJson('/api/orders/1')->assertUnauthorized();

        foreach ([$vendor, $admin] as $user) {
            $this->actingAs($user, 'sanctum')
                ->postJson('/api/checkout')
                ->assertForbidden();
        }

        // Order history only requires authentication (admins/vendors just see
        // their own, usually empty, list).
        $this->actingAs($vendor, 'sanctum')
            ->getJson('/api/orders')
            ->assertOk()
            ->assertJsonPath('data.orders.meta.total', 0);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_validates_payment_method_and_order_limits(): void
    {
        $customer = $this->customer();
        $product = $this->visibleProduct();
        $this->stockFor($product, 10);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/checkout', ['payment_method' => 'bitcoin'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_method');

        // Shrink the configured ceiling and overflow it with three cart lines.
        config()->set('marketplace.checkout.max_items_per_order', 2);

        $cart = Cart::factory()->create(['user_id' => $customer->id]);
        CartItem::factory()->count(3)->create(['cart_id' => $cart->id]);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/checkout')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cart' => 'An order may contain at most 2 items.']);

        $this->assertDatabaseCount('orders', 0);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => UserRole::CUSTOMER]);
    }

    private function visibleProduct(array $attributes = []): Product
    {
        $store = Store::factory()->create(['status' => StoreStatus::APPROVED]);

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
}
