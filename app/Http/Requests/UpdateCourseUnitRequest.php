<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCourseUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A re-uploaded document is a new version rather than a replacement, so the
     * course keeps its identity and the earlier marking stays re-derivable.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'required', 'string', 'max:50'],
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'org_unit_id' => ['sometimes', 'nullable', 'integer'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'credit_units' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'level' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:12'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Give the course a short code, for example "MATH101".',
            'code.max' => 'Keep the course code under 50 characters.',
            'title.required' => 'Give the course a title, for example "Mathematics".',
            'title.max' => 'Keep the course title under 200 characters.',
            'org_unit_id.integer' => 'Choose a department or class from your own structure, or leave it empty.',
            'description.max' => 'Keep the description under 2000 characters.',
            'credit_units.numeric' => 'Credit units must be a number, for example 3.',
            'credit_units.min' => 'Credit units cannot be negative.',
            'credit_units.max' => 'A course cannot carry more than 100 credit units.',
            'level.integer' => 'The level must be a whole number, for example 1 for year one.',
            'level.min' => 'Levels start at 1.',
            'level.max' => 'The level cannot go above 12.',
            'is_active.boolean' => 'Say yes or no when asked whether this course is still being taught.',
        ];
    }
}