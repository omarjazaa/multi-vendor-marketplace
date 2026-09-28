<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShowCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The role:customer route middleware decides who may look;
        // query-shape rules live here.
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            // Optional coupon preview: GET /api/cart?coupon=SAVE10
            'coupon' => ['nullable', 'string', 'max:32'],
        ];
    }
}
