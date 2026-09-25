<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Customer role is enforced by the role:customer route middleware;
        // product visibility and stock rules live in the controller/service.
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => [
                'required',
                'integer',
                'min:1',
                'max:'.(int) config('marketplace.cart.max_quantity_per_item'),
            ],
        ];
    }
}
