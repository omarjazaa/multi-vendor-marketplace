<?php

namespace App\Services\Pricing;

/**
 * Immutable pricing snapshot produced by PricingService, ready to be handed
 * to the HTTP layer. All money values are pre-formatted as 2dp strings to
 * match the rest of the API's monetary fields.
 */
final class CartSummary
{
    /**
     * @param  float  $subtotal  raw sum of the cart lines
     * @param  float  $discount  combined automatic + coupon reduction
     * @param  float  $tax  tax computed on the discounted amount
     * @param  float  $total  subtotal - discount + tax
     * @param  float  $taxRate  rate the tax was computed with
     * @param  array{code: string, discount: float}|null  $coupon  applied coupon, if any
     */
    public function __construct(
        public readonly float $subtotal,
        public readonly float $discount,
        public readonly float $tax,
        public readonly float $total,
        public readonly float $taxRate,
        public readonly ?array $coupon = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'subtotal' => self::money($this->subtotal),
            'discount' => self::money($this->discount),
            'tax' => self::money($this->tax),
            'total' => self::money($this->total),
            'tax_rate' => $this->taxRate,
            'coupon' => $this->coupon === null ? null : [
                'code' => $this->coupon['code'],
                'discount' => self::money($this->coupon['discount']),
            ],
        ];
    }

    private static function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
