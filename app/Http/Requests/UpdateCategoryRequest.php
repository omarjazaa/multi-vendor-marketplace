<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes', 'required', 'string', 'alpha_dash', 'max:255',
                Rule::unique('categories', 'slug')->ignore($category),
            ],
            'parent_id' => [
                'nullable', 'integer', 'exists:categories,id', Rule::notIn([$category?->id]),
            ],
        ];
    }
}
