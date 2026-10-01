<?php

namespace App\Http\Requests;

use App\Models\OrgUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAcademicUnitRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'string', Rule::in(OrgUnit::TYPES)],
            'code' => ['nullable', 'string', 'max:50'],
            'parent_id' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
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
            'type.required' => 'Say what kind of unit this is, for example faculty, department or class.',
            'type.in' => 'A unit can only be a faculty, school, department, programme or class. Please choose one of those.',
            'code.max' => 'Keep the short code under 50 characters.',
            'parent_id.integer' => 'Choose the unit this one sits under, or leave it empty for a top level unit.',
            'is_active.boolean' => 'Say yes or no when asked whether this unit is still in use.',
        ];
    }
}