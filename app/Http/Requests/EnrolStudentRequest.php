<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EnrolStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The existence check here is a shape check on the id. Whether the student
     * belongs to the acting user's institution, and whether they are already on
     * the paper, is the service's call, so the refusal reads like an explanation
     * rather than a constraint failure.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'student_id.required' => 'Choose the student who is sitting this paper.',
            'student_id.exists' => 'We could not find that student on the register. Please register them first, or pick someone from your own roster.',
        ];
    }
}