<?php

namespace Tests\Unit;

use App\Enums\DiscountType;
use App\Exceptions\InvalidCouponException;
use App\Models\Coupon;
use App\Services\Pricing\BasePrice;
use App\Services\Pricing\CouponDecorator;
use App\Services\Pricing\DiscountDecorator;
use App\Services\Pricing\TaxDecorator;
use Tests\TestCase;

class PricingDecoratorsTest extends TestCase
{
    public function test_base_price_reports_the_subtotal_with_an_empty_breakdown(): void
    {
        $price = new BasePrice(19.99);

        $this->assertSame(19.99, $price->amount());
        $this->assertSame(['discount' => 0.0, 'tax' => 0.0], $price->breakdown());
    }

    public function test_base_price_never_reports_a_negative_amount(): void
    {
        $this->assertSame(0.0, (new BasePrice(-5.0))->amount());
    }

    public function test_percentage_discount_reduces_the_running_amount(): void
    {
        $price = new DiscountDecorator(
            new BasePrice(200.0),
            DiscountType::PERCENTAGE,
            25.0,
        );

        $this->assertSame(150.0, $price->amount());
        $this->assertSame(['discount' => 50.0, 'tax' => 0.0], $price->breakdown());
    }

    public function test_fixed_discount_is_capped_at_the_running_amount(): void
    {
        $price = new DiscountDecorator(
            new BasePrice(30.0),
            DiscountType::FIXED,
            50.0,
        );

        $this->assertSame(0.0, $price->amount());
        $this->assertSame(['discount' => 30.0, 'tax' => 0.0], $price->breakdown());
    }

    public function test_coupon_percentage_discount_wraps_the_running_price(): void
    {
        $coupon = $this->coupon(['type' => DiscountType::PERCENTAGE, 'value' => 10]);

        $price = new CouponDecorator(new BasePrice(200.0), $coupon);

        $this->assertEqualsWithDelta(180.0, $price->amount(), 0.001);
        $this->assertEqualsWithDelta(20.0, $price->breakdown()['discount'], 0.001);
    }

    public function test_coupon_fixed_discount_is_capped_at_the_running_amount(): void
    {
        $coupon = $this->coupon(['type' => DiscountType::FIXED, 'value' => 100]);

        $price = new CouponDecorator(new BasePrice(40.0), $coupon);

        $this->assertSame(0.0, $price->amount());
        $this->assertSame(['discount' => 40.0, 'tax' => 0.0], $price->breakdown());
    }

    public function test_tax_is_computed_on_the_discounted_amount(): void
    {
        $price = new TaxDecorator(
            new DiscountDecorator(new BasePrice(100.0), DiscountType::PERCENTAGE, 10.0),
            0.05,
        );

        // 100 → 10% off → 90 → 5% tax (4.50) → 94.50
        $this->assertSame(94.5, $price->amount());
        $this->assertSame(['discount' => 10.0, 'tax' => 4.5], $price->breakdown());
    }

    public function test_full_chain_matches_hand_computed_values(): void
    {
        $component = new BasePrice(59.97);
        // 10% off → 5.997 rounds to 6.00 → 53.97
        $component = new DiscountDecorator($component, DiscountType::PERCENTAGE, 10.0);
        // fixed 5.00 coupon → 48.97
        $component = new CouponDecorator($component, $this->coupon([
            'type' => DiscountType::FIXED,
            'value' => 5,
        ]));
        // 8.25% tax on 48.97 → 4.04 → 53.01
        $component = new TaxDecorator($component, 0.0825);

        $this->assertEqualsWithDelta(53.01, $component->amount(), 0.001);
        $this->assertEqualsWithDelta(11.0, $component->breakdown()['discount'], 0.001);
        $this->assertEqualsWithDelta(4.04, $component->breakdown()['tax'], 0.001);
    }

    public function test_a_full_discount_coupon_zeroes_the_total_even_with_tax(): void
    {
        $component = new TaxDecorator(
            new CouponDecorator(
                new BasePrice(25.0),
                $this->coupon(['type' => DiscountType::PERCENTAGE, 'value' => 100]),
            ),
            0.05,
        );

        $this->assertSame(0.0, $component->amount());
        $this->assertSame(0.0, $component->breakdown()['tax']);
        $this->assertEqualsWithDelta(25.0, $component->breakdown()['discount'], 0.001);
    }

    public function test_expired_coupons_are_rejected_before_pricing(): void
    {
        $this->expectException(InvalidCouponException::class);
        $this->expectExceptionMessage('This coupon has expired.');

        new CouponDecorator(
            new BasePrice(100.0),
            $this->coupon(['expires_at' => now()->subDay()]),
        );
    }

    public function test_exhausted_coupons_are_rejected_before_pricing(): void
    {
        $this->expectException(InvalidCouponException::class);
        $this->expectExceptionMessage('This coupon has reached its usage limit.');

        new CouponDecorator(
            new BasePrice(100.0),
            $this->coupon(['usage_limit' => 3, 'used_count' => 3]),
        );
    }

    public function test_coupons_below_their_minimum_order_are_rejected(): void
    {
        $this->expectException(InvalidCouponException::class);
        $this->expectExceptionMessage('A minimum order of 500.00 is required to use this coupon.');

        new CouponDecorator(
            new BasePrice(100.0),
            $this->coupon(['min_order_amount' => 500]),
        );
    }

    public function test_a_negative_tax_rate_is_treated_as_zero(): void
    {
        $price = new TaxDecorator(new BasePrice(100.0), -0.05);

        $this->assertSame(100.0, $price->amount());
        $this->assertSame(0.0, $price->breakdown()['tax']);
    }

    /** Build an unsaved, eligible-by-default coupon for decorator tests. */
    private function coupon(array $attributes = []): Coupon
    {
        return new Coupon([
            'code' => 'TEST-CODE',
            'type' => DiscountType::PERCENTAGE,
            'value' => 10,
            'expires_at' => null,
            'usage_limit' => null,
            'used_count' => 0,
            'min_order_amount' => null,
            ...$attributes,
        ]);
    }
}
