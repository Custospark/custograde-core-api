<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAcademicUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The type is deliberately absent here. Changing it in place would reinterpret
     * everything beneath it, so the service refuses that and the type is set once
     * at creation. Use the move action to change where a unit sits.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Give this part of your structure a name, for example "Faculty of Science".',
            'name.max' => 'Keep the name under 150 characters.',
            'code.max' => 'Keep the short code under 50 characters.',
            'is_active.boolean' => 'Say yes or no when asked whether this unit is still in use.',
        ];
    }
}