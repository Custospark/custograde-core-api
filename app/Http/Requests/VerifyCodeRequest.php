<?php

namespace App\Http\Requests;

use App\Models\VerificationCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyCodeRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'string', 'digits:6'],
            'purpose' => ['required', 'string', Rule::in([VerificationCode::PURPOSE_EMAIL_VERIFICATION])],
        ];
    }
}
