<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DetachTeacherRequest extends FormRequest
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
            'teacher_id' => ['required', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'teacher_id.required' => 'Choose the teacher you want to remove from this course.',
            'teacher_id.integer' => 'Choose a teacher from your own institution.',
        ];
    }
}