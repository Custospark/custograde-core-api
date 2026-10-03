<?php

namespace App\Http\Resources;

use App\Models\ExamQuestion;
use App\Models\Script;
use App\Models\ScriptAnswer;
use App\Services\OmrReadingService;
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
     * The objective readings for this script, or an empty list when there are
     * none to give.
     *
     * Kept out of toArray so the cost is only paid for a script that actually has
     * a scan, and so an unreadable scan cannot take down the whole payload: a
     * marker still needs the handwriting if the bubbles cannot be read.
     *
     * @return array<int, array<string, mixed>>
     */
    private function omrReadings(): array
    {
        $script = $this->resource;

        if (! $script instanceof Script || $script->original_path === null) {
            return [];
        }

        $readings = app(OmrReadingService::class)->forScript($script);

        if ($readings === []) {
            return [];
        }

        // Returned as a list with the question number attached, because a marker
        // reads "question 7" and not "question id 412".
        $numbers = ExamQuestion::query()
            ->where('exam_id', $script->exam_id)
            ->pluck('number', 'id');

        return collect($readings)
            ->map(fn (array $reading): array => [
                'question_id' => $reading['question_id'],
                'number' => $numbers[$reading['question_id']] ?? null,
                'status' => $reading['status'],
                'option' => $reading['option'],
                'confidence' => $reading['confidence'],
            ])
            ->values()
            ->all();
    }

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

            // What the objective section says the candidate filled in. A reading,
            // not a mark: the marker still confirms it, the same as a
            // transcription (BR-02). Absent entirely when the exam has no bubbled
            // questions or the scan has not been read, so the client can tell
            // "nothing to read" from "read and found nothing marked".
            'omr' => $this->when(
                $this->original_path !== null,
                fn (): array => $this->omrReadings()
            ),

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
