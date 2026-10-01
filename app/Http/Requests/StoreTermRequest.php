<?php

namespace App\Http\Requests;

use App\Models\Term;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTermRequest extends FormRequest
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
            'academic_year_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:100'],
            'sequence' => ['required', 'integer', 'min:1', 'max:12'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date'],
            'status' => ['nullable', 'string', Rule::in(Term::STATUSES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'academic_year_id.required' => 'Choose the academic year this term belongs to.',
            'academic_year_id.integer' => 'Choose an academic year from your own calendar.',
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