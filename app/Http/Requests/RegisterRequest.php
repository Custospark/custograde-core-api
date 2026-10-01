<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
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
        $institutional = $this->isInstitutional();

        return [
            'account_type' => ['required', 'string', Rule::in(User::ACCOUNT_TYPES)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'privacy_consent' => ['required', 'accepted'],
            'institution_name' => $institutional ? ['required', 'string', 'max:255'] : ['nullable', 'string', 'max:255'],
            'institution_type' => $institutional ? ['required', 'string', 'max:100'] : ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'privacy_consent.accepted' => 'You must agree to the Data and Privacy Policy to create an account.',
            'account_type.in' => 'Choose either a personal account or an institutional account.',
            'institution_name.required' => 'Enter your institution name.',
            'institution_type.required' => 'Choose the type of institution.',
        ];
    }

    /**
     * Institution fields are only meaningful for institutional accounts, and an
     * unknown account_type must not be treated as institutional.
     */
    protected function isInstitutional(): bool
    {
        return $this->input('account_type') === User::ACCOUNT_TYPE_INSTITUTIONAL;
    }
}
