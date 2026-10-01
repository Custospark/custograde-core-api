<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A teacher correcting what the machine read (OCR-04).
 */
class CorrectTranscriptionRequest extends FormRequest
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
            'corrected_text' => ['nullable', 'string', 'max:8000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'corrected_text.max' => 'Please shorten the corrected answer to 8000 characters.',
        ];
    }
}
