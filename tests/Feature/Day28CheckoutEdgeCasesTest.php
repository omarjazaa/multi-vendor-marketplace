<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Day 28: Comprehensive Testing Sprint — Checkout & Multi-Vendor Invariants.
 *
 * Verifies complex multi-vendor order splitting, largest-remainder proportional discount
 * allocations across >=3 vendors, boundary zero-stock inventory decrements, atomic rollback
 * safety, and zero-payable order handling.
 */
class Day28CheckoutEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'marketplace.pricing.tax_rate' => 0.05,
            'marketplace.pricing.site_discount_type' => 'percentage',
            'marketplace.pricing.site_discount_value' => 0,
            'marketplace.checkout.fraud.max_order_total' => 2000,
            'marketplace.checkout.fraud.max_orders_per_window' => 10,
            'marketplace.checkout.fraud.window_minutes' => 60,
        ]);
    }

    public function test_multivendor_checkout_with_three_distinct_vendors_and_proportional_discount(): void
    {
        $customer = $this->customer();

        $storeAlpha = Store::factory()->create(['name' => 'Store Alpha', 'status' => StoreStatus::APPROVED]);
        $storeBeta = Store::factory()->create(['name' => 'Store Beta', 'status' => StoreStatus::APPROVED]);
        $storeGamma = Store::factory()->create(['name' => 'Store Gamma', 'status' => StoreStatus::APPROVED]);

        $prodAlpha = $this->productIn($storeAlpha, ['name' => 'Alpha Gadget', 'base_price' => '50.00']);
        $prodBeta = $this->productIn($storeBeta, ['name' => 'Beta Apparel', 'base_price' => '30.00']);
        $prodGamma = $this->productIn($storeGamma, ['name' => 'Gamma Tool', 'base_price' => '20.00']);

        $this->stockFor($prodAlpha, 10);
        $this->stockFor($prodBeta, 10);
        $this->stockFor($prodGamma, 10);

        Coupon::factory()->fixed()->create(['code' => 'TRIO30', 'value' => 30]);

        // Customer puts items from all 3 stores in cart (Subtotal: 50*2 + 30*1 + 20*1 = 150.00)
        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/cart/items', ['product_id' => $prodAlpha->id, 'quantity' => 2])
            ->assertCreated();
        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/cart/items', ['product_id' => $prodBeta->id, 'quantity' => 1])
            ->assertCreated();
        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/cart/items', ['product_id' => $prodGamma->id, 'quantity' => 1])
            ->assertCreated();

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/checkout', [
                'payment_method' => 'card',
                'coupon' => 'TRIO30',
            ])
            ->assertCreated();

        // 3 distinct vendor orders should be generated
        $response->assertJsonCount(3, 'data.orders');
        $this->assertDatabaseCount('orders', 3);

        $orders = Order::where('user_id', $customer->id)->with('items')->get();
        $this->assertCount(3, $orders);

        // Alpha order: subtotal 100.00 -> 2/3 of 30 discount = 20.00
        $orderAlpha = $orders->firstWhere('store_id', $storeAlpha->id);
        $this->assertNotNull($orderAlpha);
        $this->assertEquals(20.00, (float) $orderAlpha->discount);

        // Beta order: subtotal 30.00 -> 30/150 of 30 discount = 6.00
        $orderBeta = $orders->firstWhere('store_id', $storeBeta->id);
        $this->assertNotNull($orderBeta);
        $this->assertEquals(6.00, (float) $orderBeta->discount);

        // Gamma order: subtotal 20.00 -> 20/150 of 30 discount = 4.00
        $orderGamma = $orders->firstWhere('store_id', $storeGamma->id);
        $this->assertNotNull($orderGamma);
        $this->assertEquals(4.00, (float) $orderGamma->discount);

        // Total discounts allocated across vendors must exactly equal 30.00
        $totalDiscountAllocated = (float) $orderAlpha->discount + (float) $orderBeta->discount + (float) $orderGamma->discount;
        $this->assertEquals(30.00, $totalDiscountAllocated);

        // Cart must be cleared after successful checkout
        $this->assertDatabaseEmpty('cart_items');
    }

    public function test_checkout_when_inventory_is_exact_boundary_depletes_stock_to_zero(): void
    {
        $customer = $this->customer();
        $store = Store::factory()->create(['status' => StoreStatus::APPROVED]);
        $product = $this->productIn($store, ['base_price' => '25.00']);
        $inventory = $this->stockFor($product, 4);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 4])
            ->assertCreated();

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/checkout', ['payment_method' => 'cod'])
            ->assertCreated();

        // Exact depletion to 0
        $this->assertDatabaseHas('inventories', [
            'id' => $inventory->id,
            'quantity' => 0,
        ]);

        // Trying to add again to cart should fail with conflict/stock validation
        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1])
            ->assertStatus(409);
    }

    public function test_checkout_with_full_discount_handles_zero_payable_gracefully(): void
    {
        $customer = $this->customer();
        $store = Store::factory()->create(['status' => StoreStatus::APPROVED]);
        $product = $this->productIn($store, ['base_price' => '50.00']);
        $this->stockFor($product, 5);

        // 100% fixed discount coupon
        Coupon::factory()->fixed()->create(['code' => 'FREE100', 'value' => 50]);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1])
            ->assertCreated();

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/checkout', [
                'payment_method' => 'card',
                'coupon' => 'FREE100',
            ])
            ->assertCreated();

        $order = Order::where('user_id', $customer->id)->first();
        $this->assertNotNull($order);
        // Subtotal was 50, discount capped at 50, tax 0.00, total_price 0.00
        $this->assertEquals(50.00, (float) $order->discount);
        $this->assertEquals(0.00, (float) $order->total_price);
        $this->assertEquals(OrderStatus::PENDING, $order->status);
    }

    public function test_checkout_atomic_rollback_on_insufficient_stock_preserves_cart_and_creates_no_orders(): void
    {
        $customer = $this->customer();
        $storeA = Store::factory()->create(['status' => StoreStatus::APPROVED]);
        $storeB = Store::factory()->create(['status' => StoreStatus::APPROVED]);

        $prodA = $this->productIn($storeA, ['base_price' => '20.00']);
        $prodB = $this->productIn($storeB, ['base_price' => '30.00']);

        $invA = $this->stockFor($prodA, 5);
        $invB = $this->stockFor($prodB, 2);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/cart/items', ['product_id' => $prodA->id, 'quantity' => 2])
            ->assertCreated();
        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/cart/items', ['product_id' => $prodB->id, 'quantity' => 2])
            ->assertCreated();

        // Simulating a race condition: another customer bought prodB before this checkout ran
        $invB->update(['quantity' => 1]);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/checkout', ['payment_method' => 'card'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Insufficient stock during checkout.');

        // Crucial invariant: 0 orders created, storeA inventory NOT decremented, cart preserved
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart_items', 2);
        $this->assertDatabaseHas('inventories', ['id' => $invA->id, 'quantity' => 5]);
    }

    public function test_checkout_payload_validation_rejects_invalid_payment_and_unknown_coupons(): void
    {
        $customer = $this->customer();
        $store = Store::factory()->create(['status' => StoreStatus::APPROVED]);
        $prod = $this->productIn($store, ['base_price' => '20.00']);
        $this->stockFor($prod, 5);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/cart/items', ['product_id' => $prod->id, 'quantity' => 1])
            ->assertCreated();

        // Invalid payment method
        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/checkout', ['payment_method' => 'unsupported_cryptocurrency'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_method');

        // Non-existent coupon code
        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/checkout', [
                'payment_method' => 'card',
                'coupon' => 'NONEXISTENT_CODE_XYZ',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Coupon cannot be applied.')
            ->assertJsonPath('errors.coupon', 'No coupon found for code NONEXISTENT_CODE_XYZ.');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_api_v1_checkout_returns_consistent_json_resource_envelope(): void
    {
        $customer = $this->customer();
        $store = Store::factory()->create(['status' => StoreStatus::APPROVED]);
        $prod = $this->productIn($store, ['base_price' => '40.00']);
        $this->stockFor($prod, 5);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/cart/items', ['product_id' => $prod->id, 'quantity' => 1])
            ->assertCreated();

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/checkout', ['payment_method' => 'bank_transfer'])
            ->assertCreated();

        $response->assertJsonStructure([
            'data' => [
                'orders' => [
                    [
                        'id', 'user_id', 'store_id', 'status', 'payment_method',
                        'discount', 'tax', 'total_price', 'items',
                    ],
                ],
                'payment' => [
                    'method', 'successful', 'results',
                ],
            ],
            'message',
        ]);

        $this->assertSame('bank_transfer', $response->json('data.payment.method'));
        $this->assertTrue($response->json('data.payment.successful'));
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => UserRole::CUSTOMER]);
    }

    private function productIn(Store $store, array $attributes = []): Product
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
}
