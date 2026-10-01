<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Flagging a script for investigation (REV-14).
 */
class FlagScriptRequest extends FormRequest
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
            'note' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.required' => 'Please say why this script is being flagged, so the examinations officer can act on it.',
            'note.max' => 'Please shorten the note to 2000 characters.',
        ];
    }
}
