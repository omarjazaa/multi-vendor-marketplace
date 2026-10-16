<?php

namespace App\Http\Requests;

use App\Enums\ReportType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VendorReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::enum(ReportType::class)],
            'parameters' => ['nullable', 'array'],
            'parameters.limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'parameters.from' => ['nullable', 'date'],
            'parameters.to' => ['nullable', 'date', 'after_or_equal:parameters.from'],
            'parameters.days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }
}
