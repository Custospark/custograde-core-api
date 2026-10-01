<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttachTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The teacher id is only checked for shape here. Whether that person belongs
     * to the acting user's institution, and whether they may teach this course,
     * is a business rule the service owns so it can explain the refusal.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'teacher_id' => ['required', 'integer'],
            'is_responsible' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'teacher_id.required' => 'Choose the teacher you want to attach to this course.',
            'teacher_id.integer' => 'Choose a teacher from your own institution.',
            'is_responsible.boolean' => 'Say yes or no when asked to make this teacher the responsible lecturer for the course.',
        ];
    }
}