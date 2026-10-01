<?php

namespace App\Http\Resources;

use App\Models\Script;
use App\Models\ScriptAnswer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One captured script as the review workspace needs it.
 *
 * The storage path is never exposed. The browser fetches the image through a
 * short-lived signed URL, so an unpublished examination cannot be read by
 * anyone who guesses a filename (ARC-04).
 *
 * In a blind-marked examination the candidate is omitted entirely rather than
 * hidden in the client (STU-07), so a marker never receives an identity they
 * were not meant to see.
 */
class ScriptResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $blind = (bool) $this->exam?->blind_marking;
        $flagged = $this->status === Script::STATUS_FLAGGED;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'exam_id' => $this->exam_id,
            'status' => $this->status,
            // Identity is withheld in blind marking, and while a script is
            // flagged, so a marker cannot be drawn to a suspected case.
            'student' => $this->when(! $blind && ! $flagged, fn (): array => [
                'id' => $this->student?->id,
                'reg_no' => $this->student?->reg_no,
                'first_name' => $this->student?->first_name,
                'last_name' => $this->student?->last_name,
                'full_name' => $this->student?->fullName(),
                'class_name' => $this->student?->class_name,
            ]),
            'student_id' => $this->when(! $blind && ! $flagged, fn () => $this->student_id),
            'needs_identification' => $this->student_id === null,

            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'page_count' => $this->page_count,
            'expected_page_count' => $this->expected_page_count,
            'has_missing_pages' => $this->page_count < $this->expected_page_count,

            'total_mark' => (float) $this->total_mark,
            'max_mark' => (float) $this->max_mark,
            'question_count' => $this->whenCounted('answers'),
            'undecided_count' => $this->whenCounted('answers', fn (): int => $this->countUndecided()),
            'proposal_count' => $this->whenCounted('answers', fn (): int => $this->countProposals()),
            'percent_complete' => $this->whenCounted('answers', fn (): int => $this->percentComplete()),
            'is_complete' => $this->whenCounted('answers', fn (): bool => $this->isComplete()),
            'is_locked' => $this->isLocked(),
            'is_flagged' => $flagged,
            'flag_note' => $this->when($flagged, fn () => $this->flag_note),
            'risk_labels' => $this->riskLabels(),

            'locked_at' => $this->locked_at,
            'locked_by' => $this->whenNotNull($this->locked_by),
            'answers' => ScriptAnswerResource::collection($this->whenLoaded('answers')),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
