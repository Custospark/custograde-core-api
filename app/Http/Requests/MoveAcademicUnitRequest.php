<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MoveAcademicUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * parent_id is nullable rather than required because a null parent is a
     * meaningful instruction: it makes the unit top level. Laravel treats a
     * present-but-empty value as null, so both sending null and leaving the key
     * out means the same thing here.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'parent_id' => ['present', 'nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parent_id.present' => 'Say where this unit should sit: give the unit it moves under, or send nothing to make it top level.',
            'parent_id.integer' => 'Choose the unit this one sits under from your own structure, or send nothing to make it top level.',
        ];
    }
}