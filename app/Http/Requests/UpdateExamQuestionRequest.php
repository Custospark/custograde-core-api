<?php

namespace App\Http\Requests;

use App\Models\ExamQuestion;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExamQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Everything is optional. A question is usually edited one field at a time
     * while the paper is being built, so requiring the whole set would make a
     * single typo in a prompt mean re-sending everything.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'number' => ['sometimes', 'required', 'integer', 'min:1'],
            'prompt' => ['sometimes', 'required', 'string', 'max:5000'],
            'kind' => ['sometimes', 'required', 'string', 'in:'.implode(',', ExamQuestion::KINDS)],
            'max_mark' => ['sometimes', 'required', 'numeric', 'min:0.5'],
            'granularity' => ['sometimes', 'required', 'numeric', 'min:0.1', 'max:1'],
            'model_answer' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'guide_points' => ['sometimes', 'nullable', 'array', 'max:50'],
            'guide_points.*.label' => ['required', 'string', 'max:255'],
            'guide_points.*.marks' => ['required', 'numeric', 'min:0'],
            'guide_points.*.keywords' => ['sometimes', 'nullable', 'array'],
            'options' => ['sometimes', 'nullable', 'array', 'max:26'],
            'answer_key' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'number.min' => 'Question numbers start at 1.',
            'prompt.required' => 'A question cannot go back to having no wording. Please type it as candidates should see it.',
            'kind.in' => 'Choose one of: '.implode(', ', ExamQuestion::KINDS).'.',
            'max_mark.numeric' => 'The maximum mark must be a number, for example 5 or 2.5.',
            'max_mark.min' => 'A question must be worth at least half a mark.',
            'granularity.min' => 'The marking step cannot be finer than a tenth of a mark.',
            'granularity.max' => 'The marking step cannot be coarser than 1 mark.',
            'model_answer.max' => 'Keep the model answer under 5000 characters.',
            'guide_points.*.label.required' => 'Each markable point needs a short description of what earns the mark.',
            'guide_points.*.marks.required' => 'Each markable point needs the marks it is worth.',
            'options.max' => 'A multiple choice question cannot have more than 26 options.',
        ];
    }
}