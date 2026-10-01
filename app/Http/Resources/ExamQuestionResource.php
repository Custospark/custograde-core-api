<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One question on a paper (EXM-02, EXM-03).
 *
 * guide_points is the structured marking guide rather than prose, because AIG-05
 * measures the machine proposal against those points and REV-04 requires a
 * teacher to be able to change one point at a time. answer_key is only meaningful
 * for an objective question, and is sent as stored: the paper builder needs it
 * even in draft, where the marking screens would never show it.
 */
class ExamQuestionResource extends JsonResource
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
            'number' => (int) $this->number,
            'prompt' => $this->prompt,
            'kind' => $this->kind,
            'max_mark' => (float) $this->max_mark,
            'granularity' => (float) $this->granularity,
            'model_answer' => $this->model_answer,
            'guide_points' => $this->guide_points,
            'options' => $this->options,
            'answer_key' => $this->answer_key,
        ];
    }
}