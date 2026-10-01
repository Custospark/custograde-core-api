<?php

namespace App\Http\Requests;

use App\Models\Script;
use App\Services\ScriptCaptureService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A scanned script upload (CAP-01, CAP-06).
 */
class CaptureScriptRequest extends FormRequest
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
            'file' => [
                'required',
                'file',
                'max:'.ScriptCaptureService::MAX_UPLOAD_KILOBYTES,
                'mimetypes:'.implode(',', ScriptCaptureService::ACCEPTED_MIME_TYPES),
            ],
            // Optional. A paper uploaded before the operator knows whose it is
            // is kept and flagged rather than refused, because refusing it
            // would lose the script (IDN-05).
            'student_id' => ['nullable', 'integer'],
            'expected_page_count' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Choose a photograph or scan of the script to upload.',
            'file.max' => 'That file is larger than 10 MB. Please upload one page at a time, or reduce the scan quality.',
            'file.mimetypes' => 'We cannot read that file as a scanned script. Please upload a photograph or a scan in PNG, JPG or PDF format.',
            'student_id.integer' => 'That is not a valid candidate.',
            'expected_page_count.min' => 'A script has at least one page.',
        ];
    }
}
