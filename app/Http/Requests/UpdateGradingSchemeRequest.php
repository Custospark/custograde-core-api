<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGradingSchemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Bands are replaced through their own action rather than here, because a
     * band set is only valid as a whole. Swapping the bands for a new set is a
     * single write, so it gets its own endpoint and its own transaction.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'pass_mark' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'effective_from' => ['sometimes', 'required', 'date'],
            'effective_to' => ['sometimes', 'nullable', 'date'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Give the grading scheme a name, for example "Ugandan Secondary O Level".',
            'name.max' => 'Keep the grading scheme name under 150 characters.',
            'pass_mark.required' => 'Enter the lowest mark that counts as a pass, for example 50.',
            'pass_mark.numeric' => 'The pass mark must be a number between 0 and 100.',
            'pass_mark.min' => 'The pass mark cannot be below 0.',
            'pass_mark.max' => 'The pass mark cannot be above 100.',
            'description.max' => 'Keep the description under 2000 characters.',
            'effective_from.required' => 'Enter the date this grading scheme starts being used, for example 2026-01-05.',
            'effective_from.date' => 'Enter the start date as a real date, for example 2026-01-05.',
            'effective_to.date' => 'Enter the end date as a real date, for example 2026-12-18.',
            'is_default.boolean' => 'Say yes or no when asked to make this the grading scheme used by default.',
        ];
    }
}