<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A paper.
 *
 * questions and student_count are only present when the caller asked for them,
 * because a list of twenty papers would otherwise carry every question and a
 * count query apiece. The denormalised total_marks and question_count are always
 * here, which is the point of keeping them on the row.
 */
class ExamResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'exam_date' => $this->exam_date?->toDateString(),
            'duration_minutes' => $this->duration_minutes,
            'total_marks' => (int) $this->total_marks,
            'question_count' => (int) $this->question_count,
            'status' => $this->status,
            'blind_marking' => (bool) $this->blind_marking,
            'results_visible_to_students' => (bool) $this->results_visible_to_students,
            'course_unit_id' => $this->course_unit_id,
            'term_id' => $this->term_id,
            'grading_scheme_id' => $this->grading_scheme_id,
            'institution_id' => $this->institution_id,
            'owner_user_id' => $this->owner_user_id,
            'course_unit' => $this->whenLoaded('courseUnit', fn () => $this->courseUnit === null
                ? null
                : [
                    'id' => $this->courseUnit->id,
                    'code' => $this->courseUnit->code,
                    'title' => $this->courseUnit->title,
                ]),
            'term' => $this->whenLoaded('term', fn () => $this->term === null
                ? null
                : [
                    'id' => $this->term->id,
                    'name' => $this->term->name,
                ]),
            'questions' => $this->whenLoaded('questions', fn () => ExamQuestionResource::collection(
                $this->questions
            )),
            // How many candidates are entered, which is what a teacher wants
            // before deciding whether a paper is ready to set.
            'student_count' => $this->when(isset($this->students_count), fn () => (int) $this->students_count),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}