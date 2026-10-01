<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A compiled result (MRK-01, MRK-04, MRK-08, MRK-09).
 *
 * `version` is exposed because a result is a record and a record can be
 * corrected. An institution that defended version 1 must still be able to show
 * it, which is why nothing here is ever overwritten.
 */
class ResultResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exam_id' => $this->exam_id,
            'student_id' => $this->student_id,
            'script_id' => $this->script_id,
            'student' => $this->whenLoaded('student', fn (): array => [
                'id' => $this->student?->id,
                'reg_no' => $this->student?->reg_no,
                'full_name' => $this->student?->fullName(),
                'class_name' => $this->student?->class_name,
            ]),
            'total_mark' => (float) $this->total_mark,
            'max_mark' => (float) $this->max_mark,
            'percentage' => $this->percentage,
            'grade' => $this->grade,
            'grade_remark' => $this->grade_remark,
            'is_pass' => $this->is_pass,
            'status' => $this->status,
            'release_status' => $this->release_status,
            'version' => (int) $this->version,
            'grading_scheme_id' => $this->grading_scheme_id,
            'finalised_at' => $this->finalised_at,
            'released_at' => $this->released_at,
            'created_at' => $this->created_at,
        ];
    }
}
