<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderFulfilmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_order_management_endpoints(): void
    {
        $order = $this->orderWithLine($this->customer(), $this->productFor($this->vendor()));

        $this->getJson('/api/vendor/orders')->assertUnauthorized();
        $this->patchJson("/api/vendor/orders/{$order->id}/status", ['status' => 'paid'])
            ->assertUnauthorized();
        $this->getJson('/api/admin/orders')->assertUnauthorized();
        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'paid'])
            ->assertUnauthorized();
    }

    public function test_customer_cannot_access_vendor_order_endpoints(): void
    {
        $customer = $this->customer();
        $order = $this->orderWithLine($customer, $this->productFor($this->vendor()));

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/vendor/orders')
            ->assertForbidden();

        $this->actingAs($customer, 'sanctum')
            ->patchJson("/api/vendor/orders/{$order->id}/status", ['status' => 'paid'])
            ->assertForbidden();
    }

    public function test_vendor_cannot_access_admin_order_endpoints(): void
    {
        $vendor = $this->vendor();
        $order = $this->orderWithLine($this->customer(), $this->productFor($vendor));

        $this->actingAs($vendor, 'sanctum')
            ->getJson('/api/admin/orders')
            ->assertForbidden();

        $this->actingAs($vendor, 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'paid'])
            ->assertForbidden();
    }

    public function test_vendor_sees_only_their_own_order_lines_with_buyer_context(): void
    {
        $vendor = $this->vendor();
        $rival = $this->vendor();
        $buyer = $this->customer();
        $own = $this->orderWithLine($buyer, $this->productFor($vendor), [
            'line' => ['name' => 'Own Lamp', 'unit_price' => '19.99', 'quantity' => 2],
        ]);
        $this->orderWithLine($buyer, $this->productFor($rival), [
            'line' => ['name' => 'Rival Mug', 'unit_price' => '5.00', 'quantity' => 1],
        ]);

        $response = $this->actingAs($vendor, 'sanctum')
            ->getJson('/api/vendor/orders')
            ->assertOk();

        $response->assertJsonStructure([
            'data' => [
                'lines' => [
                    'data' => [[
                        'id', 'order_id', 'order_status', 'order_created_at',
                        'buyer' => ['id', 'name'],
                        'product_id', 'name', 'unit_price', 'quantity', 'line_total',
                    ]],
                    'links' => ['first', 'last', 'prev', 'next'],
                    'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                ],
            ],
            'message',
        ]);

        $response
            ->assertJsonPath('message', 'Success.')
            ->assertJsonCount(1, 'data.lines.data')
            ->assertJsonPath('data.lines.data.0.order_id', $own->id)
            ->assertJsonPath('data.lines.data.0.order_status', 'pending')
            ->assertJsonPath('data.lines.data.0.buyer.id', $buyer->id)
            ->assertJsonPath('data.lines.data.0.buyer.name', $buyer->name)
            ->assertJsonPath('data.lines.data.0.name', 'Own Lamp')
            ->assertJsonPath('data.lines.data.0.unit_price', '19.99')
            ->assertJsonPath('data.lines.data.0.quantity', 2)
            ->assertJsonPath('data.lines.data.0.line_total', '39.98') // 2 × 19.99
            ->assertJsonPath('data.lines.meta.total', 1);
    }

    public function test_vendor_can_filter_order_lines_by_status(): void
    {
        $vendor = $this->vendor();
        $buyer = $this->customer();
        $product = $this->productFor($vendor);
        $this->orderWithLine($buyer, $product, ['order' => ['status' => OrderStatus::PAID]]);
        $this->orderWithLine($buyer, $product, ['order' => ['status' => OrderStatus::PENDING]]);

        $this->actingAs($vendor, 'sanctum')
            ->getJson('/api/vendor/orders?status=paid')
            ->assertOk()
            ->assertJsonCount(1, 'data.lines.data')
            ->assertJsonPath('data.lines.data.0.order_status', 'paid');

        $this->actingAs($vendor, 'sanctum')
            ->getJson('/api/vendor/orders?status=bogus')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_admin_sees_every_order_with_items(): void
    {
        $admin = $this->admin();
        $first = $this->orderWithLine($this->customer(), $this->productFor($this->vendor()), [
            'order' => ['created_at' => now()->subDay()],
        ]);
        $second = $this->orderWithLine($this->customer(), $this->productFor($this->vendor()));

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/orders')
            ->assertOk();

        $response->assertJsonStructure([
            'data' => [
                'orders' => [
                    'data' => [[
                        'id', 'user_id', 'status', 'payment_method', 'total_price',
                        'items', 'created_at', 'updated_at',
                    ]],
                    'links' => ['first', 'last', 'prev', 'next'],
                    'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                ],
            ],
            'message',
        ]);

        $response
            ->assertJsonCount(2, 'data.orders.data')
            ->assertJsonPath('data.orders.data.0.id', $second->id)
            ->assertJsonPath('data.orders.data.1.id', $first->id)
            ->assertJsonCount(1, 'data.orders.data.0.items')
            ->assertJsonPath('data.orders.meta.total', 2);
    }

    public function test_admin_can_filter_orders_by_status(): void
    {
        $admin = $this->admin();
        $this->orderWithLine($this->customer(), $this->productFor($this->vendor()), [
            'order' => ['status' => OrderStatus::SHIPPED],
        ]);
        $this->orderWithLine($this->customer(), $this->productFor($this->vendor()), [
            'order' => ['status' => OrderStatus::PENDING],
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/orders?status=shipped')
            ->assertOk()
            ->assertJsonCount(1, 'data.orders.data')
            ->assertJsonPath('data.orders.data.0.status', 'shipped');
    }

    public function test_vendor_can_transition_an_order_containing_their_line(): void
    {
        $vendor = $this->vendor();
        $order = $this->orderWithLine($this->customer(), $this->productFor($vendor), [
            'order' => ['status' => OrderStatus::PAID],
        ]);

        $response = $this->actingAs($vendor, 'sanctum')
            ->patchJson("/api/vendor/orders/{$order->id}/status", ['status' => 'shipped'])
            ->assertOk();

        $response
            ->assertJsonPath('message', 'Order status updated.')
            ->assertJsonPath('data.order.id', $order->id)
            ->assertJsonPath('data.order.status', 'shipped');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'shipped']);
    }

    public function test_vendor_cannot_transition_an_order_without_their_lines(): void
    {
        $vendor = $this->vendor();
        $rival = $this->vendor();
        $order = $this->orderWithLine($this->customer(), $this->productFor($rival));

        $this->actingAs($vendor, 'sanctum')
            ->patchJson("/api/vendor/orders/{$order->id}/status", ['status' => 'cancelled'])
            ->assertForbidden();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
    }

    public function test_customer_cannot_transition_their_own_order(): void
    {
        $customer = $this->customer();
        $order = $this->orderWithLine($customer, $this->productFor($this->vendor()));

        // Role middleware rejects customers before the policy is consulted.
        $this->actingAs($customer, 'sanctum')
            ->patchJson("/api/vendor/orders/{$order->id}/status", ['status' => 'cancelled'])
            ->assertForbidden();

        $this->actingAs($customer, 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'cancelled'])
            ->assertForbidden();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
    }

    public function test_admin_can_transition_any_order(): void
    {
        $admin = $this->admin();
        $order = $this->orderWithLine($this->customer(), $this->productFor($this->vendor()));

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'paid'])
            ->assertOk();

        $response->assertJsonPath('data.order.status', 'paid');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
    }

    public function test_invalid_transition_is_rejected_with_409(): void
    {
        $admin = $this->admin();
        $order = $this->orderWithLine($this->customer(), $this->productFor($this->vendor()), [
            'order' => ['status' => OrderStatus::PENDING],
        ]);

        // pending → delivered skips paid and shipped, so the map refuses it.
        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'delivered'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Order status transition not allowed.')
            ->assertJsonPath('errors.status', 'An order cannot move from pending to delivered.');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
    }

    public function test_terminal_statuses_cannot_move(): void
    {
        $admin = $this->admin();
        $order = $this->orderWithLine($this->customer(), $this->productFor($this->vendor()), [
            'order' => ['status' => OrderStatus::CANCELLED],
        ]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'paid'])
            ->assertStatus(409);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
    }

    public function test_unknown_status_value_is_rejected_with_422(): void
    {
        $admin = $this->admin();
        $order = $this->orderWithLine($this->customer(), $this->productFor($this->vendor()));

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'teleported'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
    }

    public function test_missing_order_returns_404_on_transition_routes(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/admin/orders/999999/status', ['status' => 'paid'])
            ->assertNotFound();
    }

    private function vendor(): User
    {
        return User::factory()->create(['role' => UserRole::VENDOR]);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => UserRole::CUSTOMER]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::ADMIN]);
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
}
