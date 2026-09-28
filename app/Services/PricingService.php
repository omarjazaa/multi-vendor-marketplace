<?php

namespace App\Services;

use App\Enums\DiscountType;
use App\Exceptions\InvalidCouponException;
use App\Models\Cart;
use App\Repositories\Contracts\CouponRepositoryInterface;
use App\Services\Pricing\BasePrice;
use App\Services\Pricing\CartSummary;
use App\Services\Pricing\CouponDecorator;
use App\Services\Pricing\DiscountDecorator;
use App\Services\Pricing\PriceComponentInterface;
use App\Services\Pricing\TaxDecorator;

/**
 * Composes the Decorator-pattern price pipeline for a cart — base subtotal,
 * automatic site discount, optional coupon, then tax — and reports the
 * subtotal/discount/tax/total breakdown behind the Cart summary endpoint.
 */
class PricingService
{
    public function __construct(private readonly CouponRepositoryInterface $coupons) {}

    /**
     * Price the given cart, optionally previewing a coupon code.
     *
     * @throws InvalidCouponException when the code is unknown or the coupon is ineligible
     */
    public function summarize(Cart $cart, ?string $couponCode = null): CartSummary
    {
        $subtotal = round(
            $cart->items->sum(
                fn ($item): float => (float) $item->unit_price * (int) $item->quantity,
            ),
            2,
        );

        $component = $this->applySiteDiscount(new BasePrice($subtotal));

        $coupon = null;
        $couponDiscount = 0.0;

        $code = strtoupper(trim((string) $couponCode));
        if ($code !== '') {
            $coupon = $this->coupons->findByCode($code);
            if ($coupon === null) {
                throw InvalidCouponException::notFound($code);
            }

            $beforeCoupon = $component->amount();
            $component = new CouponDecorator($component, $coupon);
            $couponDiscount = round($beforeCoupon - $component->amount(), 2);
        }

        $taxRate = (float) config('marketplace.pricing.tax_rate', 0);
        $component = new TaxDecorator($component, $taxRate);
        $breakdown = $component->breakdown();

        return new CartSummary(
            subtotal: $subtotal,
            discount: $breakdown['discount'],
            tax: $breakdown['tax'],
            total: $component->amount(),
            taxRate: $taxRate,
            coupon: $coupon === null
                ? null
                : ['code' => $coupon->code, 'discount' => $couponDiscount],
        );
    }

    /** Wrap the price with the config-driven site discount when one is active. */
    private function applySiteDiscount(PriceComponentInterface $component): PriceComponentInterface
    {
        $type = DiscountType::tryFrom(
            (string) config('marketplace.pricing.site_discount_type', 'percentage'),
        );
        $value = (float) config('marketplace.pricing.site_discount_value', 0);

        if ($type === null || $value <= 0) {
            return $component;
        }

        return new DiscountDecorator($component, $type, $value);
    }
}
