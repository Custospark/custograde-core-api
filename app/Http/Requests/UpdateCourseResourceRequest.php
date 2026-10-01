<?php

namespace App\Http\Requests;

use App\Models\CourseResource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourseResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * There is no file field here on purpose. A new document is a new version, so
     * the stored file is never replaced by an edit; only the details change.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'kind' => ['sometimes', 'required', 'string', Rule::in(CourseResource::KINDS)],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Give the document a title, for example "Mathematics Past Paper 2025".',
            'title.max' => 'Keep the document title under 200 characters.',
            'kind.required' => 'Say what kind of document this is, for example a past paper or a marking guide.',
            'kind.in' => 'Say what kind of document this is, for example a past paper or a marking guide.',
            'description.max' => 'Keep the description under 2000 characters.',
        ];
    }
}