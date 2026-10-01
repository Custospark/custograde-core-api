<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReplaceGradeBandsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A band set is only valid as a whole, so the whole set is required and
     * validated together. Gaps and overlaps across the set are checked by the
     * service, which can name every problem at once.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bands' => ['required', 'array', 'min:1', 'max:100'],
            'bands.*.grade' => ['required', 'string', 'max:10'],
            'bands.*.min_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'bands.*.max_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'bands.*.grade_point' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'bands.*.remark' => ['nullable', 'string', 'max:255'],
            'bands.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bands.required' => 'Send the full list of grade bands, because the previous bands are replaced by the new ones.',
            'bands.array' => 'The grade bands must be sent as a list.',
            'bands.min' => 'A grading scheme needs at least one grade band, otherwise no mark can be given a grade.',
            'bands.max' => 'That is more than 100 grade bands. Please check the list you sent.',
            'bands.*.grade.required' => 'Every grade band needs a grade letter, for example A, B or C.',
            'bands.*.grade.max' => 'Keep the grade letter under 10 characters.',
            'bands.*.min_percent.required' => 'Every grade band needs the lowest mark it covers, for example 70.',
            'bands.*.min_percent.numeric' => 'The lowest mark in a band must be a number between 0 and 100.',
            'bands.*.min_percent.min' => 'A band cannot start below 0.',
            'bands.*.min_percent.max' => 'A band cannot start above 100.',
            'bands.*.max_percent.required' => 'Every grade band needs the highest mark it covers, for example 100.',
            'bands.*.max_percent.numeric' => 'The highest mark in a band must be a number between 0 and 100.',
            'bands.*.max_percent.min' => 'A band cannot end below 0.',
            'bands.*.max_percent.max' => 'A band cannot end above 100.',
            'bands.*.grade_point.numeric' => 'The grade point must be a number between 0 and 100.',
            'bands.*.grade_point.min' => 'The grade point cannot be below 0.',
            'bands.*.grade_point.max' => 'The grade point cannot be above 100.',
            'bands.*.remark.max' => 'Keep the remark under 255 characters.',
            'bands.*.sort_order.integer' => 'The band order must be a whole number.',
            'bands.*.sort_order.min' => 'The band order cannot be below 0.',
        ];
    }
}