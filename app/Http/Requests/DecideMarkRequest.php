<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A teacher's decision on one mark (REV-01, REV-07).
 */
class DecideMarkRequest extends FormRequest
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
            // The maximum and granularity are checked against the question in
            // MarkingService, because only there is the paper's own scale known.
            'value' => ['required', 'numeric', 'min:0'],
            'feedback' => ['nullable', 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'value.required' => 'Enter the mark for this question before saving.',
            'value.numeric' => 'A mark has to be a number, for example 3 or 3.5.',
            'value.min' => 'A mark cannot be less than zero.',
            'feedback.max' => 'Please shorten the feedback to 2000 characters.',
        ];
    }
}
