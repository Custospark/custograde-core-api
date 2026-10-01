<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGradingSchemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Shape and range only. Whether the bands actually cover 0 to 100 without a
     * gap or an overlap is a business rule, so the service checks it and returns
     * a message naming every problem it found.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'pass_mark' => ['required', 'numeric', 'min:0', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            // Nullable in the schema: a scheme created today may be in force
            // from the start of the current term, which is expressed by leaving
            // the date empty rather than by guessing a start.
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date'],
            'is_default' => ['nullable', 'boolean'],
            'bands' => ['nullable', 'array', 'min:1', 'max:100'],
            'bands.*.grade' => ['required_with:bands', 'string', 'max:10'],
            'bands.*.min_percent' => ['required_with:bands', 'numeric', 'min:0', 'max:100'],
            'bands.*.max_percent' => ['required_with:bands', 'numeric', 'min:0', 'max:100'],
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
            'name.required' => 'Give the grading scheme a name, for example "Ugandan Secondary O Level".',
            'name.max' => 'Keep the grading scheme name under 150 characters.',
            'pass_mark.required' => 'Enter the lowest mark that counts as a pass, for example 50.',
            'pass_mark.numeric' => 'The pass mark must be a number between 0 and 100.',
            'pass_mark.min' => 'The pass mark cannot be below 0.',
            'pass_mark.max' => 'The pass mark cannot be above 100.',
            'description.max' => 'Keep the description under 2000 characters.',
            'effective_from.required' => 'Enter the date this grading scheme starts being used, for example 2026-01-05.',
            'effective_from.date' => 'Enter the start date as a real date, for example 2026-01-05.',
            'effective_to.date' => 'Enter the end date as a real date, for example 2026-12-18.',
            'is_default.boolean' => 'Say yes or no when asked to make this the grading scheme used by default.',
            'bands.array' => 'The grade bands must be sent as a list.',
            'bands.min' => 'A grading scheme needs at least one grade band, otherwise no mark can be given a grade.',
            'bands.max' => 'That is more than 100 grade bands. Please check the list you sent.',
            'bands.*.grade.required_with' => 'Every grade band needs a grade letter, for example A, B or C.',
            'bands.*.grade.max' => 'Keep the grade letter under 10 characters.',
            'bands.*.min_percent.required_with' => 'Every grade band needs the lowest mark it covers, for example 70.',
            'bands.*.min_percent.numeric' => 'The lowest mark in a band must be a number between 0 and 100.',
            'bands.*.min_percent.min' => 'A band cannot start below 0.',
            'bands.*.min_percent.max' => 'A band cannot start above 100.',
            'bands.*.max_percent.required_with' => 'Every grade band needs the highest mark it covers, for example 100.',
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