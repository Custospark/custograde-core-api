<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating a member of staff inside an institution (AUT-05, GOV-05).
 *
 * Registration is deliberately not reused. Self-registration creates the first
 * administrator of a new institution and nothing else, because allowing anyone to
 * mint themselves an examination officer would make the whole capability matrix
 * decorative. Staff are added by someone who already holds
 * MANAGE_INSTITUTION_USERS.
 */
class StoreInstitutionUserRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', 'string', Rule::in($this->assignableRoles())],
        ];
    }

    /**
     * Roles this institution may hand out.
     *
     * `system_admin` is absent, and not by accident: it is the recovery role for
     * a tenant that has locked itself out, so it must not be reachable from inside
     * a tenant. Granting it over the API would mean any administrator could create
     * an account that answers to nobody in their own institution.
     *
     * @return list<string>
     */
    public function assignableRoles(): array
    {
        return [
            User::ROLE_INSTITUTION_ADMIN,
            User::ROLE_EXAMINATION_OFFICER,
            User::ROLE_TEACHER,
            User::ROLE_MODERATOR,
            User::ROLE_SCANNING_OPERATOR,
            User::ROLE_AUDITOR,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.in' => 'That role cannot be assigned from inside an institution.',
            'email.unique' => 'Somebody with that email address already has an account.',
        ];
    }
}