<?php

namespace App\Http\Requests;

use App\Enums\DiscountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Role gating (admin/vendor) is enforced by route middleware;
        // payload shape and value rules live here.
        return true;
    }

    /** Normalise the code before validation so the unique rule sees the stored form. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'code' => [
                'required', 'string', 'min:3', 'max:32',
                'regex:/^[A-Z0-9_-]+$/', 'unique:coupons,code',
            ],
            'type' => ['required', Rule::enum(DiscountType::class)],
            'value' => [
                'required', 'numeric', 'min:0.01',
                // Percentages above 100 would pay the customer to order.
                Rule::when(
                    $this->input('type') === DiscountType::PERCENTAGE->value,
                    ['max:100'],
                ),
            ],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
