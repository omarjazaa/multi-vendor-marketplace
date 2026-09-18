<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplyForStoreRequest extends FormRequest
{
    /**
     * The vendor middleware is responsible for role authorization.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Define store application validation rules.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'alpha_dash', 'max:255', 'unique:stores,slug'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
