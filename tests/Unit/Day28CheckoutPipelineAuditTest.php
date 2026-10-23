<?php

namespace Tests\Unit;

use App\Enums\DiscountType;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidCouponException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Inventory;
use App\Models\Product;
use App\Services\Checkout\CheckoutContext;
use App\Services\Checkout\CheckoutValidationHandler;
use App\Services\Checkout\CouponValidityCheck;
use App\Services\Checkout\StockAvailabilityCheck;
use App\Services\Pricing\BasePrice;
use App\Services\Pricing\DiscountDecorator;
use App\Services\Pricing\TaxDecorator;
use ArrayObject;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Day 28: Comprehensive Testing Sprint — Checkout Chain of Responsibility & Pricing Decorator Engine Audit.
 *
 * Verifies pipeline execution ordering, short-circuit halting on rejection, individual handler
 * validation rules, and mathematical precision and zero-floor bounding in decorator compositions.
 */
class Day28CheckoutPipelineAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'marketplace.checkout.fraud.max_order_total' => 1000,
            'marketplace.checkout.fraud.max_orders_per_window' => 5,
            'marketplace.checkout.fraud.window_minutes' => 60,
        ]);
    }

    public function test_pipeline_halts_at_first_rejection_and_skips_subsequent_links(): void
    {
        $log = new ArrayObject;
        $haltException = new RuntimeException('First link failed.');

        $link1 = new class($log, $haltException) extends CheckoutValidationHandler
        {
            public function __construct(private ArrayObject $log, private RuntimeException $ex) {}

            protected function validate(CheckoutContext $context): void
            {
                $this->log->append('link1');
                throw $this->ex;
            }
        };

        $link2 = new class($log) extends CheckoutValidationHandler
        {
            public function __construct(private ArrayObject $log) {}

            protected function validate(CheckoutContext $context): void
            {
                $this->log->append('link2');
            }
        };

        $link1->setNext($link2);

        $cart = Cart::factory()->create();
        $context = new CheckoutContext($cart, new EloquentCollection, null, 'card');

        try {
            $link1->handle($context);
            $this->fail('Pipeline should have halted at link 1.');
        } catch (RuntimeException $e) {
            $this->assertSame($haltException, $e);
        }

        $this->assertSame(['link1'], $log->getArrayCopy());
    }

    public function test_stock_availability_check_handler_detects_inventory_deficit(): void
    {
        $handler = app(StockAvailabilityCheck::class);

        $product = Product::factory()->create(['name' => 'Mechanical Keyboard']);
        Inventory::factory()->create(['product_id' => $product->id, 'quantity' => 2]);

        $cart = Cart::factory()->create();
        $item = new CartItem(['product_id' => $product->id, 'quantity' => 5]);
        $item->setRelation('product', $product);

        $context = new CheckoutContext($cart, new EloquentCollection([$item]), null, 'card');

        try {
            $handler->handle($context);
            $this->fail('Expected InsufficientStockException was not thrown.');
        } catch (InsufficientStockException $exception) {
            $this->assertSame('Mechanical Keyboard', $exception->productName);
            $this->assertSame(2, $exception->available);
        }
    }

    public function test_coupon_validity_check_handler_enforces_eligibility(): void
    {
        $handler = app(CouponValidityCheck::class);

        $expiredCoupon = Coupon::factory()->create([
            'code' => 'EXPIRED20',
            'expires_at' => now()->subDays(2),
        ]);

        $cart = Cart::factory()->create();
        $product = Product::factory()->create(['base_price' => '100.00']);
        $item = new CartItem(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => '100.00']);
        $item->setRelation('product', $product);

        $context = new CheckoutContext($cart, new EloquentCollection([$item]), 'EXPIRED20', 'card');

        try {
            $handler->handle($context);
            $this->fail('Expected InvalidCouponException for expired coupon.');
        } catch (InvalidCouponException $exception) {
            $this->assertSame('This coupon has expired.', $exception->getMessage());
        }
    }

    public function test_pricing_decorators_composition_order_and_breakdown_accuracy(): void
    {
        // 1. Base price of 100.00
        $base = new BasePrice(100.00);
        $this->assertSame(100.00, $base->amount());

        // 2. 20% Discount decorator -> 80.00
        $discounted = new DiscountDecorator($base, DiscountType::PERCENTAGE, 20.0);
        $this->assertEqualsWithDelta(80.00, $discounted->amount(), 0.001);
        $this->assertEqualsWithDelta(20.00, $discounted->breakdown()['discount'], 0.001);

        // 3. Tax decorator at 10% on discounted amount (10% of 80 = 8.00) -> Total 88.00
        $taxed = new TaxDecorator($discounted, 0.10);
        $this->assertEqualsWithDelta(88.00, $taxed->amount(), 0.001);
        $this->assertEqualsWithDelta(8.00, $taxed->breakdown()['tax'], 0.001);
        $this->assertEqualsWithDelta(20.00, $taxed->breakdown()['discount'], 0.001);
    }

    public function test_pricing_decorators_clamp_to_zero_and_prevent_negative_amounts(): void
    {
        $base = new BasePrice(40.00);

        // Fixed discount of 100.00 on a 40.00 base should floor to 0.00
        $floored = new DiscountDecorator($base, DiscountType::FIXED, 100.00);

        $this->assertSame(0.0, $floored->amount());
        $this->assertSame(40.0, $floored->breakdown()['discount']);

        // Tax on 0.00 remains 0.00
        $taxed = new TaxDecorator($floored, 0.15);
        $this->assertSame(0.0, $taxed->amount());
        $this->assertSame(0.0, $taxed->breakdown()['tax']);
    }
}
