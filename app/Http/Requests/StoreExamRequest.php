<?php

namespace App\Http\Requests;

use App\Models\Exam;
use Illuminate\Foundation\Http\FormRequest;

class StoreExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The exam catalogue is EXM-01, and the rule list is the model constant so
     * the accepted set cannot drift from the one the lifecycle understands.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'type' => ['required', 'string', 'in:'.implode(',', Exam::TYPES)],
            'course_unit_id' => ['required', 'integer', 'exists:course_units,id'],
            'term_id' => ['nullable', 'integer', 'exists:terms,id'],
            'exam_date' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'grading_scheme_id' => ['nullable', 'integer', 'exists:grading_schemes,id'],
            'blind_marking' => ['nullable', 'boolean'],
            'results_visible_to_students' => ['nullable', 'boolean'],
            // The exams table carries no description column, so this is accepted
            // and shape checked for the paper builder form but not stored. It is
            // here rather than absent so the client form has one contract to
            // build against when the column lands with a later migration.
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Give the paper a title, for example "Mathematics End of Term 1".',
            'title.max' => 'Keep the paper title under 200 characters.',
            'type.required' => 'Choose what kind of paper this is.',
            'type.in' => 'Choose one of: '.implode(', ', Exam::TYPES).'.',
            'course_unit_id.required' => 'Choose the course this paper is for.',
            'course_unit_id.exists' => 'We could not find that course in your institution. Please pick a course from your own list.',
            'term_id.exists' => 'We could not find that term in your institution. Please choose a term from your own calendar, or leave it empty.',
            'exam_date.required' => 'Enter the date candidates will sit this paper.',
            'exam_date.date' => 'Enter the exam date as a real date, for example 2026-03-18.',
            'duration_minutes.integer' => 'The duration must be a whole number of minutes.',
            'duration_minutes.min' => 'A paper cannot be shorter than a minute.',
            'duration_minutes.max' => 'A paper cannot run for longer than 24 hours (1440 minutes).',
            'grading_scheme_id.exists' => 'We could not find that grading scheme in your institution. Please choose one of your own schemes.',
            'blind_marking.boolean' => 'Say yes or no when asked whether markers should see candidates\' names.',
            'results_visible_to_students.boolean' => 'Say yes or no when asked whether candidates may see their own results for this paper.',
            'description.max' => 'Keep the description under 2000 characters.',
        ];
    }
}