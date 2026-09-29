<?php

namespace App\Services\Checkout;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Mutable state travelling down the checkout validation chain.
 *
 * Created once per checkout attempt by the order service: the chain is
 * handed the context, handlers reject by throwing a typed exception, and
 * an accepted checkout leaves its findings here — the resolved coupon and
 * the cart subtotal — for the pricing/writing step that follows.
 */
final class CheckoutContext
{
    /** @var Collection<int, CartItem> cart lines with their products eager loaded */
    public readonly Collection $lines;

    /** Raw sum of the cart lines, the base every eligibility rule measures. */
    public readonly float $subtotal;

    /** The coupon the chain resolved and accepted, if a code was supplied. */
    public ?Coupon $coupon = null;

    /**
     * @param  Collection<int, CartItem>  $lines  lines already loaded with their products
     * @param  string|null  $couponCode  coupon code supplied with the request
     */
    public function __construct(
        public readonly Cart $cart,
        Collection $lines,
        public readonly ?string $couponCode,
        public readonly ?string $paymentMethod,
    ) {
        $this->lines = $lines;
        $this->subtotal = round(
            $lines->sum(
                fn (CartItem $line): float => (float) $line->unit_price * (int) $line->quantity,
            ),
            2,
        );
    }

    /** The customer whose cart is being checked out. */
    public function userId(): int
    {
        return (int) $this->cart->user_id;
    }
}
