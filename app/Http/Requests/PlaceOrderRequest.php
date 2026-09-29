<?php

namespace App\Http\Requests;

use App\Models\Cart;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Customer role comes from the route middleware; the cart is validated
        // below and stock rules live in the order service transaction.
        return true;
    }

    /**
     * Normalise the coupon code before validation so the checkout chain and
     * the pricing decorator both look up the stored (uppercase) form.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('coupon'))) {
            $this->merge(['coupon' => strtoupper(trim($this->input('coupon')))]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'payment_method' => [
                'nullable',
                'string',
                'max:50',
                Rule::in((array) config('marketplace.checkout.payment_methods')),
            ],
            // Optional coupon applied at checkout: { "coupon": "SAVE10" }.
            // Eligibility (expiry, usage, minimum) is decided by the
            // checkout validation chain, not by shape rules here.
            'coupon' => ['nullable', 'string', 'max:32'],
        ];
    }

    /** Reject carts that outgrow the configured per-order item ceiling. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $max = (int) config('marketplace.checkout.max_items_per_order');
            $count = Cart::where('user_id', $this->user()->id)
                ->withCount('items')
                ->first()?->items_count ?? 0;

            if ($count > $max) {
                $validator->errors()->add('cart', "An order may contain at most {$max} items.");
            }
        });
    }
}
