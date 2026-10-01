<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GradeBandResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'grading_scheme_id' => $this->grading_scheme_id,
            'grade' => $this->grade,
            'grade_point' => $this->grade_point,
            'remark' => $this->remark,
            'min_percent' => (float) $this->min_percent,
            'max_percent' => (float) $this->max_percent,
            'sort_order' => $this->sort_order === null ? null : (int) $this->sort_order,
        ];
    }
}