<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var array{default_low_stock_threshold: int, max_quantity: int} $inventory */
        $inventory = config('marketplace.inventory');

        return [
            'quantity' => ['required', 'integer', 'min:0', 'max:'.$inventory['max_quantity']],
            'low_stock_threshold' => ['sometimes', 'integer', 'min:0', 'max:'.$inventory['max_quantity']],
        ];
    }
}
