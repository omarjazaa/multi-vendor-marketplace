<?php

namespace Tests\Feature;

use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShoppingCartTest extends TestCase
{
    use RefreshDatabase;

    public function test_customers_can_view_an_empty_cart_with_the_standard_envelope(): void
    {
        $customer = $this->customer();

        $response = $this->actingAs($customer, 'sanctum')
            ->getJson('/api/cart')
            ->assertOk();

        $response->assertJsonStructure([
            'data' => [
                'cart' => [
                    'id', 'user_id', 'items', 'total_quantity', 'total_price',
                    'created_at', 'updated_at',
                ],
            ],
            'message',
        ]);

        $response
            ->assertJsonPath('message', 'Success.')
            ->assertJsonPath('data.cart.user_id', $customer->id)
            ->assertJsonPath('data.cart.total_quantity', 0)
            ->assertJsonPath('data.cart.total_price', '0.00')
            ->assertJsonCount(0, 'data.cart.items');

        $this->assertDatabaseHas('carts', ['user_id' => $customer->id]);
    }

    public function test_customer_can_add_a_visible_product_to_the_cart(): void
    {
        $customer = $this->customer();
        $product = $this->visibleProduct(['name' => 'Desk Lamp', 'base_price' => '49.99']);
        $this->stockFor($product, 10);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2])
            ->assertCreated();

        $response
            ->assertJsonPath('message', 'Item added to cart.')
            ->assertJsonPath('data.cart.total_quantity', 2)
            ->assertJsonPath('data.cart.total_price', '99.98')
            ->assertJsonPath('data.cart.items.0.product_id', $product->id)
            ->assertJsonPath('data.cart.items.0.quantity', 2)
            ->assertJsonPath('data.cart.items.0.unit_price', '49.99')
            ->assertJsonPath('data.cart.items.0.product.name', 'Desk Lamp')
            ->assertJsonStructure([
                'data' => [
                    'cart' => [
                        'items' => [[
                            'id', 'product_id', 'quantity', 'unit_price', 'product',
                            'created_at', 'updated_at',
                        ]],
                    ],
                ],
                'message',
            ]);

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => '49.99',
        ]);
    }

    public function test_adding_the_same_product_twice_merges_quantities(): void
    {
        $customer = $this->customer();
        $product = $this->visibleProduct();
        $this->stockFor($product, 10);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2])
            ->assertCreated();

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 3])
            ->assertCreated()
            ->assertJsonPath('data.cart.total_quantity', 5)
            ->assertJsonPath('data.cart.items.0.quantity', 5)
            ->assertJsonCount(1, 'data.cart.items');

        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_customer_can_update_a_cart_item_quantity(): void
    {
        $customer = $this->customer();
        $product = $this->visibleProduct(['base_price' => '49.99']);
        $this->stockFor($product, 10);

        $itemId = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2])
            ->assertCreated()
            ->json('data.cart.items.0.id');

        $this->actingAs($customer, 'sanctum')
            ->putJson("/api/cart/items/{$itemId}", ['quantity' => 4])
            ->assertOk()
            ->assertJsonPath('message', 'Cart updated.')
            ->assertJsonPath('data.cart.items.0.quantity', 4)
            ->assertJsonPath('data.cart.total_quantity', 4)
            ->assertJsonPath('data.cart.total_price', '199.96');

        $this->assertDatabaseHas('cart_items', ['id' => $itemId, 'quantity' => 4]);
    }

    public function test_customer_can_remove_a_cart_item_and_clear_the_cart(): void
    {
        $customer = $this->customer();
        $first = $this->visibleProduct();
        $second = $this->visibleProduct();
        $this->stockFor($first, 10);
        $this->stockFor($second, 10);

        $firstItemId = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $first->id, 'quantity' => 1])
            ->assertCreated()
            ->json('data.cart.items.0.id');

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $second->id, 'quantity' => 1])
            ->assertCreated()
            ->assertJsonCount(2, 'data.cart.items');

        $this->actingAs($customer, 'sanctum')
            ->deleteJson("/api/cart/items/{$firstItemId}")
            ->assertOk()
            ->assertJsonPath('message', 'Item removed from cart.')
            ->assertJsonCount(1, 'data.cart.items')
            ->assertJsonPath('data.cart.items.0.product_id', $second->id);

        $this->assertDatabaseMissing('cart_items', ['id' => $firstItemId]);

        $this->actingAs($customer, 'sanctum')
            ->deleteJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('message', 'Cart cleared.')
            ->assertJsonCount(0, 'data.cart.items')
            ->assertJsonPath('data.cart.total_quantity', 0);

        $this->assertDatabaseCount('cart_items', 0);

        $this->actingAs($customer, 'sanctum')
            ->deleteJson('/api/cart/items/999999')
            ->assertNotFound();
    }

    public function test_stock_limits_reject_add_and_update_with_conflict(): void
    {
        $customer = $this->customer();
        $lowStock = $this->visibleProduct();
        $this->stockFor($lowStock, 5);

        $soldOut = $this->visibleProduct();
        $this->stockFor($soldOut, 0);

        // Requesting more than the shelf holds conflicts with current stock.
        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $lowStock->id, 'quantity' => 6])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Insufficient stock for this product.')
            ->assertJsonStructure(['message', 'errors' => ['quantity']]);

        // A merged repeat add must respect the ceiling as well: 3 already held + 3 > 5.
        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $lowStock->id, 'quantity' => 3])
            ->assertCreated();

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $lowStock->id, 'quantity' => 3])
            ->assertStatus(409)
            ->assertJsonPath('errors.quantity', 'Only 5 units are available.');

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('data.cart.items.0.quantity', 3);

        $itemId = $this->actingAs($customer, 'sanctum')
            ->getJson('/api/cart')
            ->json('data.cart.items.0.id');

        $this->actingAs($customer, 'sanctum')
            ->putJson("/api/cart/items/{$itemId}", ['quantity' => 9])
            ->assertStatus(409)
            ->assertJsonStructure(['message', 'errors' => ['quantity']]);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $soldOut->id, 'quantity' => 1])
            ->assertStatus(409)
            ->assertJsonPath('errors.quantity', 'Only 0 units are available.');
    }

    public function test_invisible_products_cannot_be_added(): void
    {
        $customer = $this->customer();
        $inactive = $this->visibleProduct(['is_active' => false]);
        // StoreFactory defaults to a pending store, so this product stays hidden.
        $pendingStoreProduct = Product::factory()->create();

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $inactive->id, 'quantity' => 1])
            ->assertNotFound();

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $pendingStoreProduct->id, 'quantity' => 1])
            ->assertNotFound();

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_cart_items_are_isolated_between_users(): void
    {
        $owner = $this->customer();
        $intruder = $this->customer();
        $product = $this->visibleProduct();
        $this->stockFor($product, 10);

        $itemId = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2])
            ->assertCreated()
            ->json('data.cart.items.0.id');

        $this->actingAs($intruder, 'sanctum')
            ->putJson("/api/cart/items/{$itemId}", ['quantity' => 9])
            ->assertForbidden();

        $this->actingAs($intruder, 'sanctum')
            ->deleteJson("/api/cart/items/{$itemId}")
            ->assertForbidden();

        // The owner's line is untouched after both attempts.
        $this->assertDatabaseHas('cart_items', ['id' => $itemId, 'quantity' => 2]);
    }

    public function test_cart_routes_require_authentication_and_the_customer_role(): void
    {
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $product = $this->visibleProduct();

        $this->getJson('/api/cart')->assertUnauthorized();
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])
            ->assertUnauthorized();

        foreach ([$vendor, $admin] as $user) {
            $this->actingAs($user, 'sanctum')->getJson('/api/cart')->assertForbidden();
            $this->actingAs($user, 'sanctum')
                ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])
                ->assertForbidden();
        }

        $this->assertDatabaseCount('carts', 0);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_unit_price_is_snapshotted_at_add_time(): void
    {
        $customer = $this->customer();
        $product = $this->visibleProduct(['base_price' => '49.99']);
        $this->stockFor($product, 10);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])
            ->assertCreated()
            ->assertJsonPath('data.cart.items.0.unit_price', '49.99');

        $product->update(['base_price' => '99.99']);

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('data.cart.items.0.unit_price', '49.99')
            ->assertJsonPath('data.cart.total_price', '49.99');
    }

    public function test_cart_input_validation_rejects_bad_payloads(): void
    {
        $customer = $this->customer();
        $product = $this->visibleProduct();
        $this->stockFor($product, 10);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $max = (int) config('marketplace.cart.max_quantity_per_item');

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => $max + 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $product->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => 999999, 'quantity' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');

        $itemId = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])
            ->assertCreated()
            ->json('data.cart.items.0.id');

        $this->actingAs($customer, 'sanctum')
            ->putJson("/api/cart/items/{$itemId}", ['quantity' => 'many'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');
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
