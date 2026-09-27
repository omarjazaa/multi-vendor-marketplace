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
