<?php

namespace App\Http\Requests;

use App\Models\ExamQuestion;
use Illuminate\Foundation\Http\FormRequest;

class StoreExamQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * max_mark and granularity are per question, not per paper (AIG-05), because a
     * mark is validated against the question it belongs to and a paper total
     * cannot enforce that. granularity tops out at 1 because a mark finer than
     * whole marks is not something a school exam records.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'number' => ['required', 'integer', 'min:1'],
            'prompt' => ['required', 'string', 'max:5000'],
            'kind' => ['required', 'string', 'in:'.implode(',', ExamQuestion::KINDS)],
            'max_mark' => ['required', 'numeric', 'min:0.5'],
            'granularity' => ['required', 'numeric', 'min:0.1', 'max:1'],
            'model_answer' => ['nullable', 'string', 'max:5000'],
            'guide_points' => ['nullable', 'array', 'max:50'],
            'guide_points.*.label' => ['required', 'string', 'max:255'],
            'guide_points.*.marks' => ['required', 'numeric', 'min:0'],
            'guide_points.*.keywords' => ['nullable', 'array'],
            'options' => ['nullable', 'array', 'max:26'],
            'answer_key' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'number.required' => 'Give the question its number on the paper, starting at 1.',
            'number.min' => 'Question numbers start at 1.',
            'prompt.required' => 'Type the question as candidates should see it on the paper.',
            'kind.required' => 'Choose what kind of question this is, because that decides how it is marked.',
            'kind.in' => 'Choose one of: '.implode(', ', ExamQuestion::KINDS).'.',
            'max_mark.required' => 'Enter the highest mark this question can earn.',
            'max_mark.numeric' => 'The maximum mark must be a number, for example 5 or 2.5.',
            'max_mark.min' => 'A question must be worth at least half a mark.',
            'granularity.required' => 'Say how finely this question is marked: 1 for whole marks, 0.5 where half marks are allowed.',
            'granularity.min' => 'The marking step cannot be finer than a tenth of a mark.',
            'granularity.max' => 'The marking step cannot be coarser than 1 mark.',
            'model_answer.max' => 'Keep the model answer under 5000 characters.',
            'guide_points.*.label.required' => 'Each markable point needs a short description of what earns the mark.',
            'guide_points.*.marks.required' => 'Each markable point needs the marks it is worth.',
            'options.max' => 'A multiple choice question cannot have more than 26 options.',
        ];
    }
}