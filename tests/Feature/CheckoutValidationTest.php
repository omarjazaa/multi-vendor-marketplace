<?php

namespace Tests\Feature;

use App\Enums\DiscountType;
use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\Coupon;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Day 16 checkout validation chain and money allocation, exercised over
 * the HTTP surface: coupon acceptance/rejection, fraud heuristics, and the
 * largest-remainder split of the cart-wide discount and tax across the
 * per-vendor orders.
 */
class CheckoutValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Pin the pricing and fraud rules so the assertions below are exact.
        config([
            'marketplace.pricing.tax_rate' => 0.05,
            'marketplace.pricing.site_discount_type' => 'percentage',
            'marketplace.pricing.site_discount_value' => 0,
            'marketplace.checkout.fraud.max_order_total' => 1000,
            'marketplace.checkout.fraud.max_orders_per_window' => 5,
            'marketplace.checkout.fraud.window_minutes' => 60,
        ]);
    }

    public function test_coupon_discount_and_tax_are_allocated_across_vendors_and_match_the_preview(): void
    {
        $customer = $this->customer();
        $alpha = Store::factory()->create(['status' => StoreStatus::APPROVED]);
        $beta = Store::factory()->create(['status' => StoreStatus::APPROVED]);
        $lamp = $this->productIn($alpha, ['name' => 'Desk Lamp', 'base_price' => '49.99']);
        $mug = $this->productIn($beta, ['name' => 'Trail Mug', 'base_price' => '10.00']);
        $this->stockFor($lamp, 10);
        $this->stockFor($mug, 10);
        Coupon::factory()->fixed()->create(['code' => 'SAVE10', 'value' => 10]);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $lamp->id, 'quantity' => 2])
            ->assertCreated();
        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $mug->id, 'quantity' => 3])
            ->assertCreated();

        // Preview: 129.98 subtotal, 10.00 coupon, 6.00 tax, 125.98 total.
        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/cart?coupon=SAVE10')
            ->assertOk()
            ->assertJsonPath('data.summary.subtotal', '129.98')
            ->assertJsonPath('data.summary.discount', '10.00')
            ->assertJsonPath('data.summary.tax', '6.00')
            ->assertJsonPath('data.summary.total', '125.98');

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/checkout', ['payment_method' => 'card', 'coupon' => 'SAVE10'])
            ->assertCreated();

        // Alpha: 7.69 discount + 4.62 tax on 99.98 → 96.91.
        // Beta: 2.31 discount + 1.38 tax on 30.00 → 29.07.
        $response
            ->assertJsonCount(2, 'data.orders')
            ->assertJsonPath('data.orders.0.store_id', $alpha->id)
            ->assertJsonPath('data.orders.0.discount', '7.69')
            ->assertJsonPath('data.orders.0.tax', '4.62')
            ->assertJsonPath('data.orders.0.total_price', '96.91')
            ->assertJsonPath('data.orders.1.store_id', $beta->id)
            ->assertJsonPath('data.orders.1.discount', '2.31')
            ->assertJsonPath('data.orders.1.tax', '1.38')
            ->assertJsonPath('data.orders.1.total_price', '29.07');

        // The allocated parts sum back to the preview exactly — no cent lost.
        $totals = array_map(
            fn (array $order): float => (float) $order['total_price'],
            $response->json('data.orders'),
        );
        $this->assertSame(125.98, round(array_sum($totals), 2));

        $this->assertDatabaseHas('orders', [
            'store_id' => $alpha->id,
            'discount' => '7.69',
            'tax' => '4.62',
            'total_price' => '96.91',
        ]);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_invalid_coupons_are_rejected_at_checkout_with_the_preview_messages(): void
    {
        $customer = $this->customer();
        $product = $this->visibleProduct(['base_price' => '49.99']);
        $this->stockFor($product, 10);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])
            ->assertCreated();

        Coupon::factory()->create(['code' => 'OLD', 'expires_at' => now()->subDay()]);
        Coupon::factory()->create(['code' => 'GONE', 'usage_limit' => 1, 'used_count' => 1]);
        Coupon::factory()->fixed()->create(['code' => 'WHALE', 'value' => 10, 'min_order_amount' => 500]);

        $scenarios = [
            'NOPE' => 'No coupon found for code NOPE.',
            'OLD' => 'This coupon has expired.',
            'GONE' => 'This coupon has reached its usage limit.',
            'WHALE' => 'A minimum order of 500.00 is required to use this coupon.',
        ];

        foreach ($scenarios as $code => $message) {
            // Preview and checkout share one eligibility source, so both
            // surfaces report the identical rejection reason.
            $this->actingAs($customer, 'sanctum')
                ->getJson("/api/cart?coupon={$code}")
                ->assertUnprocessable()
                ->assertJsonPath('errors.coupon', $message);

            $this->actingAs($customer, 'sanctum')
                ->postJson('/api/checkout', ['coupon' => $code])
                ->assertUnprocessable()
                ->assertJsonPath('message', 'Coupon cannot be applied.')
                ->assertJsonPath('errors.coupon', $message);
        }

        // Every rejection happened before any write: no orders, stock and
        // cart untouched.
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseHas('inventories', ['product_id' => $product->id, 'quantity' => 10]);
    }

    public function test_checkout_blocks_a_cart_over_the_fraud_ceiling_and_names_the_rule(): void
    {
        config(['marketplace.checkout.fraud.max_order_total' => 100]);

        $customer = $this->customer();
        $product = $this->visibleProduct(['base_price' => '60.00']);
        $this->stockFor($product, 10);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2])
            ->assertCreated();

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/checkout')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Checkout rejected by rule max_order_total.')
            ->assertJsonPath('errors.fraud', 'Order total of 120.00 exceeds the allowed limit of 100.00.');

        // The chain rejected before the transaction: no stock moved, no writes.
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('inventories', ['product_id' => $product->id, 'quantity' => 10]);
        $this->assertDatabaseCount('cart_items', 1);

        // Raising the ceiling lets the same cart through, priced with tax.
        config(['marketplace.checkout.fraud.max_order_total' => 500]);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/checkout')
            ->assertCreated()
            ->assertJsonPath('data.orders.0.discount', '0.00')
            ->assertJsonPath('data.orders.0.tax', '6.00')
            ->assertJsonPath('data.orders.0.total_price', '126.00');
    }

    public function test_checkout_blocks_a_burst_of_orders_inside_the_fraud_window(): void
    {
        config([
            'marketplace.checkout.fraud.max_orders_per_window' => 1,
            'marketplace.checkout.fraud.window_minutes' => 60,
        ]);

        $customer = $this->customer();
        $product = $this->visibleProduct(['base_price' => '10.00']);
        $this->stockFor($product, 5);

        $addToCart = fn () => $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])
            ->assertCreated();

        $addToCart();
        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/checkout')
            ->assertCreated();

        // The (max + 1)-th order inside the window is blocked and the rule
        // named; the cart survives the rejection for a retry later.
        $addToCart();
        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/checkout')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Checkout rejected by rule max_orders_per_window.')
            ->assertJsonPath(
                'errors.fraud',
                'Too many orders in a short window: 1 placed within the last 60 minutes (maximum 1).',
            );

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('cart_items', 1);

        // A zero disables the heuristic entirely.
        config(['marketplace.checkout.fraud.max_orders_per_window' => 0]);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/checkout')
            ->assertCreated();

        $this->assertDatabaseCount('orders', 2);
    }

    public function test_site_discount_and_tax_reach_the_order_without_a_coupon(): void
    {
        config(['marketplace.pricing.site_discount_value' => 10]);

        $customer = $this->customer();
        $product = $this->visibleProduct(['base_price' => '49.99']);
        $this->stockFor($product, 5);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])
            ->assertCreated();

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/checkout')
            ->assertCreated()
            ->assertJsonPath('data.orders.0.discount', '5.00') // 49.99 × 10% → 4.999 rounds to 5.00
            ->assertJsonPath('data.orders.0.tax', '2.25')      // 44.99 × 5% → 2.2495 rounds to 2.25
            ->assertJsonPath('data.orders.0.total_price', '47.24');
    }

    public function test_a_full_discount_coupon_zeroes_the_split_order_totals(): void
    {
        $customer = $this->customer();
        $alpha = Store::factory()->create(['status' => StoreStatus::APPROVED]);
        $beta = Store::factory()->create(['status' => StoreStatus::APPROVED]);
        $lamp = $this->productIn($alpha, ['base_price' => '49.99']);
        $mug = $this->productIn($beta, ['base_price' => '10.00']);
        $this->stockFor($lamp, 5);
        $this->stockFor($mug, 5);
        Coupon::factory()->create([
            'code' => 'FREE',
            'type' => DiscountType::PERCENTAGE,
            'value' => 100,
        ]);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $lamp->id, 'quantity' => 1])
            ->assertCreated();
        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $mug->id, 'quantity' => 1])
            ->assertCreated();

        // Every vendor's share is its own subtotal and no tax is due.
        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/checkout', ['coupon' => 'FREE'])
            ->assertCreated()
            ->assertJsonCount(2, 'data.orders')
            ->assertJsonPath('data.orders.0.discount', '49.99')
            ->assertJsonPath('data.orders.0.tax', '0.00')
            ->assertJsonPath('data.orders.0.total_price', '0.00')
            ->assertJsonPath('data.orders.1.discount', '10.00')
            ->assertJsonPath('data.orders.1.tax', '0.00')
            ->assertJsonPath('data.orders.1.total_price', '0.00');

        $this->assertDatabaseHas('orders', ['store_id' => $alpha->id, 'total_price' => '0.00']);
        $this->assertDatabaseHas('orders', ['store_id' => $beta->id, 'total_price' => '0.00']);
    }

    public function test_coupon_codes_are_normalised_at_checkout(): void
    {
        $customer = $this->customer();
        $product = $this->visibleProduct(['base_price' => '49.99']);
        $this->stockFor($product, 5);
        Coupon::factory()->fixed()->create(['code' => 'SAVE5', 'value' => 5]);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])
            ->assertCreated();

        // Lower case and padded: the request normalises before the chain runs.
        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/checkout', ['coupon' => '  save5  '])
            ->assertCreated()
            ->assertJsonPath('data.orders.0.discount', '5.00')
            ->assertJsonPath('data.orders.0.tax', '2.25') // 44.99 × 5% → 2.2495 rounds to 2.25
            ->assertJsonPath('data.orders.0.total_price', '47.24');
    }

    public function test_a_non_string_coupon_parameter_is_rejected_at_checkout(): void
    {
        $customer = $this->customer();
        $product = $this->visibleProduct();
        $this->stockFor($product, 5);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])
            ->assertCreated();

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/checkout', ['coupon' => ['X']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('coupon');

        $this->assertDatabaseCount('orders', 0);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => UserRole::CUSTOMER]);
    }

    private function visibleProduct(array $attributes = []): Product
    {
        $store = Store::factory()->create(['status' => StoreStatus::APPROVED]);

        return $this->productIn($store, $attributes);
    }

    /** Create a product inside an existing store so carts can span vendors. */
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
