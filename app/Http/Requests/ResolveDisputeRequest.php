<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResolveDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'admin_notes' => ['nullable', 'string', 'max:1000'],
            'force' => ['nullable', 'boolean'],
        ];
    }
}
