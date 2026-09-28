<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared by GET /api/vendor/orders and GET /api/admin/orders: both accept the
 * same optional ?status= filter validated against the configured status list.
 */
class FilterOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in((array) config('marketplace.orders.filter_statuses'))],
        ];
    }
}
