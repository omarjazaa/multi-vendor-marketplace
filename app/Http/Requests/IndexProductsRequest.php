<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:255'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'availability' => ['nullable', Rule::in(['in_stock', 'low_stock', 'out_of_stock'])],
            'sort' => ['nullable', Rule::in(array_keys((array) config('marketplace.catalog.sorts')))],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:'.(int) config('marketplace.catalog.max_per_page'),
            ],
        ];
    }
}
