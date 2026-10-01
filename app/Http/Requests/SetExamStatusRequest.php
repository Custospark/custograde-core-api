<?php

namespace App\Http\Requests;

use App\Models\Exam;
use Illuminate\Foundation\Http\FormRequest;

class SetExamStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The accepted set is the model constant, because the status is more than a
     * label: it gates whether scripts may be attached and whether questions may
     * still change. A value the lifecycle does not recognise would break both.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:'.implode(',', Exam::STATUSES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Choose the stage you are moving this paper to.',
            'status.in' => 'Choose one of: '.implode(', ', Exam::STATUSES).'.',
        ];
    }
}