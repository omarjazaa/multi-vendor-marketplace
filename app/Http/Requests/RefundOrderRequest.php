<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RefundOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
            'force' => ['nullable', 'boolean'],
        ];
    }
}
