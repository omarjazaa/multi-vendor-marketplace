<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class RegisterRequest extends FormRequest
{
    /**
     * Determine whether the caller may register.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Define the registration validation rules.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', new Enum(UserRole::class)],
        ];
    }
}
