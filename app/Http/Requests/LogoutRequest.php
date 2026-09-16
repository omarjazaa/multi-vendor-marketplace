<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LogoutRequest extends FormRequest
{
    /**
     * Determine whether the authenticated caller may log out.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * There is no request payload to validate for logout.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
