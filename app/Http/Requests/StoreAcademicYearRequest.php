<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAcademicYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date'],
            'is_current' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Give the academic year a name, for example "2026".',
            'name.max' => 'Keep the academic year name short, 100 characters is plenty.',
            'starts_on.required' => 'Enter the date the academic year begins, for example 2026-01-05.',
            'starts_on.date' => 'Enter the start date as a real date, for example 2026-01-05.',
            'ends_on.required' => 'Enter the date the academic year ends, for example 2026-12-18.',
            'ends_on.date' => 'Enter the end date as a real date, for example 2026-12-18.',
            'is_current.boolean' => 'Say yes or no when asked to make this the running academic year.',
        ];
    }
}