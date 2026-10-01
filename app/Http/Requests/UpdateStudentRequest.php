<?php

namespace App\Http\Requests;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Everything is optional. A roster is corrected one field at a time, and
     * requiring the whole set would make a typo in a phone number mean retyping
     * the registration number too.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reg_no' => ['sometimes', 'required', 'string', 'max:50'],
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'class_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'status' => ['sometimes', 'string', 'in:'.implode(',', Student::STATUSES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reg_no.required' => 'A student cannot go back to having no registration number. Please enter one.',
            'reg_no.max' => 'Keep the registration number under 50 characters.',
            'first_name.required' => 'A student cannot go back to having no first name.',
            'last_name.required' => 'A student cannot go back to having no last name.',
            'class_name.max' => 'Keep the class or programme name under 100 characters.',
            'phone.max' => 'Keep the phone number under 50 characters.',
            'email.email' => 'Check the email address, it does not look like a valid one.',
            'email.max' => 'Keep the email address under 150 characters.',
            'status.in' => 'Choose one of: '.implode(', ', Student::STATUSES).'.',
        ];
    }
}