<?php

namespace App\Http\Requests;

use App\Models\Term;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The academic year a term sits in is deliberately not editable here, since
     * moving a term into a different year changes what dates are legal for it.
     * Create a term in the right year instead.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'sequence' => ['sometimes', 'required', 'integer', 'min:1', 'max:12'],
            'starts_on' => ['sometimes', 'required', 'date'],
            'ends_on' => ['sometimes', 'required', 'date'],
            'status' => ['sometimes', 'string', Rule::in(Term::STATUSES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Give the term a name, for example "Term 1".',
            'name.max' => 'Keep the term name short, 100 characters is plenty.',
            'sequence.required' => 'Say which term this is, so the terms are listed in the right order.',
            'sequence.integer' => 'The term number must be a whole number.',
            'sequence.min' => 'Term numbers start at 1.',
            'sequence.max' => 'A year cannot have more than 12 terms.',
            'starts_on.required' => 'Enter the date the term begins, for example 2026-01-05.',
            'starts_on.date' => 'Enter the start date as a real date, for example 2026-01-05.',
            'ends_on.required' => 'Enter the date the term ends, for example 2026-04-03.',
            'ends_on.date' => 'Enter the end date as a real date, for example 2026-04-03.',
            'status.in' => 'A term can be planned, running, or closed. Please pick one of those.',
        ];
    }
}