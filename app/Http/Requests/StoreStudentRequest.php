<?php

namespace App\Http\Requests;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The registration number is unique within the institution (STU-01), not
     * globally. That is checked in the service rather than here so the refusal
     * can name the number already in use.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reg_no' => ['required', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'class_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'status' => ['nullable', 'string', 'in:'.implode(',', Student::STATUSES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reg_no.required' => 'Enter the student\'s registration number. It is how their scripts are matched back to them.',
            'reg_no.max' => 'Keep the registration number under 50 characters.',
            'first_name.required' => 'Enter the student\'s first name.',
            'first_name.max' => 'Keep the first name under 100 characters.',
            'last_name.required' => 'Enter the student\'s last name.',
            'last_name.max' => 'Keep the last name under 100 characters.',
            'class_name.max' => 'Keep the class or programme name under 100 characters.',
            'phone.max' => 'Keep the phone number under 50 characters.',
            'email.email' => 'Check the email address, it does not look like a valid one.',
            'email.max' => 'Keep the email address under 150 characters.',
            'status.in' => 'Choose one of: '.implode(', ', Student::STATUSES).'.',
        ];
    }
}