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

class CartSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Pin the pricing rules so the assertions below are deterministic.
        config([
            'marketplace.pricing.tax_rate' => 0.05,
            'marketplace.pricing.site_discount_type' => 'percentage',
            'marketplace.pricing.site_discount_value' => 0,
        ]);
    }

    public function test_guests_cannot_view_the_cart_summary(): void
    {
        $this->getJson('/api/cart')->assertUnauthorized();
    }

    public function test_non_customers_cannot_view_the_cart_summary(): void
    {
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);

        $this->actingAs($vendor, 'sanctum')->getJson('/api/cart')->assertForbidden();
    }

    public function test_empty_cart_summary_reports_zeroed_amounts(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/cart')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'cart' => ['id', 'user_id', 'items', 'total_quantity', 'total_price'],
                    'summary' => ['subtotal', 'discount', 'tax', 'total', 'tax_rate', 'coupon'],
                ],
                'message',
            ])
            ->assertJsonPath('data.summary.subtotal', '0.00')
            ->assertJsonPath('data.summary.discount', '0.00')
            ->assertJsonPath('data.summary.tax', '0.00')
            ->assertJsonPath('data.summary.total', '0.00')
            ->assertJsonPath('data.summary.tax_rate', 0.05)
            ->assertJsonPath('data.summary.coupon', null);
    }

    public function test_summary_matches_cart_lines_and_applies_the_configured_tax(): void
    {
        $customer = $this->filledCart(2); // 2 × 49.99 = 99.98

        $response = $this->actingAs($customer, 'sanctum')
            ->getJson('/api/cart')
            ->assertOk();

        $response
            ->assertJsonPath('data.summary.subtotal', '99.98')
            ->assertJsonPath('data.summary.discount', '0.00')
            ->assertJsonPath('data.summary.tax', '5.00') // 99.98 × 5% → 4.999 rounds to 5.00
            ->assertJsonPath('data.summary.total', '104.98')
            ->assertJsonPath('data.summary.coupon', null)
            // The summary subtotal must agree with the legacy cart total.
            ->assertJsonPath('data.cart.total_price', '99.98');
    }

    public function test_fixed_coupon_reduces_the_total_when_previewed(): void
    {
        $customer = $this->filledCart(2); // 99.98
        Coupon::factory()->fixed()->create(['code' => 'SAVE10', 'value' => 10]);

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/cart?coupon=SAVE10')
            ->assertOk()
            ->assertJsonPath('data.summary.subtotal', '99.98')
            ->assertJsonPath('data.summary.discount', '10.00')
            ->assertJsonPath('data.summary.tax', '4.50') // 89.98 × 5% → 4.499 rounds to 4.50
            ->assertJsonPath('data.summary.total', '94.48')
            ->assertJsonPath('data.summary.coupon.code', 'SAVE10')
            ->assertJsonPath('data.summary.coupon.discount', '10.00');
    }

    public function test_percentage_coupon_discounts_the_running_total(): void
    {
        $customer = $this->filledCart(2, basePrice: '50.00'); // 100.00
        Coupon::factory()->create(['code' => 'PCT10', 'type' => DiscountType::PERCENTAGE, 'value' => 10]);

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/cart?coupon=pct10') // codes are case-insensitive
            ->assertOk()
            ->assertJsonPath('data.summary.subtotal', '100.00')
            ->assertJsonPath('data.summary.discount', '10.00')
            ->assertJsonPath('data.summary.tax', '4.50') // 90.00 × 5%
            ->assertJsonPath('data.summary.total', '94.50')
            ->assertJsonPath('data.summary.coupon.code', 'PCT10');
    }

    public function test_a_full_discount_coupon_zeroes_the_summary(): void
    {
        $customer = $this->filledCart(1); // 49.99
        Coupon::factory()->create([
            'code' => 'FREE',
            'type' => DiscountType::PERCENTAGE,
            'value' => 100,
        ]);

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/cart?coupon=FREE')
            ->assertOk()
            ->assertJsonPath('data.summary.discount', '49.99')
            ->assertJsonPath('data.summary.tax', '0.00')
            ->assertJsonPath('data.summary.total', '0.00');
    }

    public function test_an_unknown_coupon_code_is_rejected(): void
    {
        $customer = $this->filledCart(1);

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/cart?coupon=NOPE')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Coupon cannot be applied.')
            ->assertJsonPath('errors.coupon', 'No coupon found for code NOPE.');
    }

    public function test_an_expired_coupon_is_rejected(): void
    {
        $customer = $this->filledCart(1);
        Coupon::factory()->create(['code' => 'OLD', 'expires_at' => now()->subDay()]);

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/cart?coupon=OLD')
            ->assertUnprocessable()
            ->assertJsonPath('errors.coupon', 'This coupon has expired.');
    }

    public function test_an_exhausted_coupon_is_rejected(): void
    {
        $customer = $this->filledCart(1);
        Coupon::factory()->create(['code' => 'GONE', 'usage_limit' => 1, 'used_count' => 1]);

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/cart?coupon=GONE')
            ->assertUnprocessable()
            ->assertJsonPath('errors.coupon', 'This coupon has reached its usage limit.');
    }

    public function test_a_coupon_minimum_order_is_enforced(): void
    {
        $customer = $this->filledCart(2); // 99.98
        Coupon::factory()->fixed()->create(['code' => 'WHALE', 'min_order_amount' => 500]);

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/cart?coupon=WHALE')
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.coupon',
                'A minimum order of 500.00 is required to use this coupon.',
            );
    }

    public function test_automatic_site_discount_is_applied_without_a_coupon(): void
    {
        config(['marketplace.pricing.site_discount_value' => 10]);
        $customer = $this->filledCart(2); // 99.98

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('data.summary.discount', '10.00')
            ->assertJsonPath('data.summary.tax', '4.50')
            ->assertJsonPath('data.summary.total', '94.48')
            ->assertJsonPath('data.summary.coupon', null);
    }

    public function test_cart_mutations_also_return_the_live_summary(): void
    {
        $customer = $this->customer();
        $product = $this->visibleProduct(['base_price' => '49.99']);
        $this->stockFor($product, 10);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2])
            ->assertCreated()
            ->assertJsonPath('data.summary.subtotal', '99.98')
            ->assertJsonPath('data.summary.tax', '5.00')
            ->assertJsonPath('data.summary.total', '104.98');
    }

    public function test_a_non_string_coupon_parameter_is_rejected(): void
    {
        $customer = $this->filledCart(1);

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/cart?coupon[]=X')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('coupon');
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => UserRole::CUSTOMER]);
    }

    /** Put $quantity copies of a 49.99 (configurable) product into the customer's cart. */
    private function filledCart(int $quantity, string $basePrice = '49.99'): User
    {
        $customer = $this->customer();
        $product = $this->visibleProduct(['base_price' => $basePrice]);
        $this->stockFor($product, max(10, $quantity));

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => $quantity])
            ->assertCreated();

        return $customer;
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
