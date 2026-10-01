<?php

namespace App\Http\Requests;

use App\Models\CourseResource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourseResourceRequest extends FormRequest
{
    /**
     * 20 MB expressed in kilobytes, the unit a Laravel file rule expects.
     */
    public const MAX_UPLOAD_KILOBYTES = 20 * 1024;

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
            'file' => ['required', 'file', 'max:'.self::MAX_UPLOAD_KILOBYTES, 'mimetypes:'.implode(',', CourseResource::ACCEPTED_MIME_TYPES)],
            'title' => ['nullable', 'string', 'max:200'],
            'kind' => ['nullable', 'string', Rule::in(CourseResource::KINDS)],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Choose the document you want to add to the course.',
            'file.file' => 'Upload the document as a file rather than pasting its name.',
            'file.max' => 'That file is larger than 20 MB. Please upload a smaller document, or split it into parts.',
            'file.mimetypes' => 'We cannot read that file as a course document. Please upload a PDF, a Word document (.doc or .docx), or a plain text file (.txt).',
            'title.max' => 'Keep the document title under 200 characters.',
            'kind.in' => 'Say what kind of document this is, for example a past paper or a marking guide.',
            'description.max' => 'Keep the description under 2000 characters.',
        ];
    }
}