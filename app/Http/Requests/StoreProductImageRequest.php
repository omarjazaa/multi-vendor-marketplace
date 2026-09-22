<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var array{max_per_product: int, max_size_kb: int, mimes: array<int, string>} $images */
        $images = config('marketplace.products.images');

        return [
            'images' => ['required', 'array', 'min:1', 'max:'.$images['max_per_product']],
            'images.*' => [
                'required',
                'file',
                'image',
                'mimes:'.implode(',', $images['mimes']),
                'max:'.$images['max_size_kb'],
            ],
        ];
    }
}
