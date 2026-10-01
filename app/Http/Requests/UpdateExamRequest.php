<?php

namespace App\Http\Requests;

use App\Models\Exam;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Everything is optional here. The service refuses the change outright once a
     * paper is finalised or archived, so there is no partial edit to salvage.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'type' => ['sometimes', 'required', 'string', 'in:'.implode(',', Exam::TYPES)],
            'course_unit_id' => ['sometimes', 'required', 'integer', 'exists:course_units,id'],
            'term_id' => ['sometimes', 'nullable', 'integer', 'exists:terms,id'],
            'exam_date' => ['sometimes', 'required', 'date'],
            'duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1440'],
            'grading_scheme_id' => ['sometimes', 'nullable', 'integer', 'exists:grading_schemes,id'],
            'blind_marking' => ['sometimes', 'boolean'],
            'results_visible_to_students' => ['sometimes', 'boolean'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'A paper cannot go back to having no title. Please give it one, for example "Mathematics End of Term 1".',
            'title.max' => 'Keep the paper title under 200 characters.',
            'type.in' => 'Choose one of: '.implode(', ', Exam::TYPES).'.',
            'course_unit_id.exists' => 'We could not find that course in your institution. Please pick a course from your own list.',
            'term_id.exists' => 'We could not find that term in your institution. Please choose a term from your own calendar, or clear it.',
            'exam_date.date' => 'Enter the exam date as a real date, for example 2026-03-18.',
            'duration_minutes.integer' => 'The duration must be a whole number of minutes.',
            'duration_minutes.min' => 'A paper cannot be shorter than a minute.',
            'duration_minutes.max' => 'A paper cannot run for longer than 24 hours (1440 minutes).',
            'grading_scheme_id.exists' => 'We could not find that grading scheme in your institution. Please choose one of your own schemes, or clear it.',
            'blind_marking.boolean' => 'Say yes or no when asked whether markers should see candidates\' names.',
            'results_visible_to_students.boolean' => 'Say yes or no when asked whether candidates may see their own results for this paper.',
            'description.max' => 'Keep the description under 2000 characters.',
        ];
    }
}