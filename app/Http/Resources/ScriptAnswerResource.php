<?php

namespace App\Http\Resources;

use App\Models\ScriptAnswer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One answer on a script, with everything needed to decide it.
 *
 * Per REV-01 the transcription, the marking guide, the suggested mark and the
 * current mark are all present at once. Splitting them across screens is how a
 * marker approves a suggestion they have not actually read.
 *
 * `decided` is the human-in-the-loop gate, and it is deliberately stricter than
 * "has a mark": a value with no person attached does not count, so nothing can
 * become final without an authenticated human action (BR-02).
 */
class ScriptAnswerResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'script_id' => $this->script_id,
            'question_id' => $this->question_id,
            'question_number' => (int) $this->question_number,
            'question' => $this->whenLoaded('question', fn (): array => [
                'id' => $this->question?->id,
                'number' => $this->question?->number,
                'prompt' => $this->question?->prompt,
                'kind' => $this->question?->kind,
                'max_mark' => (float) ($this->question?->max_mark ?? 0),
                'granularity' => (float) ($this->question?->granularity ?? 1),
                'model_answer' => $this->question?->model_answer,
                'guide_points' => $this->question?->guide_points ?? [],
            ]),

            // OCR-04: both readings, so a disputed result can show what the
            // system saw and what the teacher changed.
            'machine_text' => $this->machine_text,
            'corrected_text' => $this->corrected_text,
            'effective_text' => $this->effectiveText(),
            'content_type' => $this->content_type,
            'transcription_confidence' => $this->transcription_confidence,
            'low_confidence_words' => $this->lowConfWords(),
            'truncated' => (bool) $this->truncated,
            'transcription_note' => $this->transcription_note,
            'transcription_model' => $this->transcription_model,

            // AIG-01: the proposal, never the mark.
            'suggested_mark' => $this->suggested_mark,
            'suggestion_confidence' => $this->suggestion_confidence,
            'suggestion_rationale' => $this->suggestion_rationale,
            'suggestion_strategy' => $this->suggestion_strategy,
            'suggestion_model' => $this->suggestion_model,
            'matched_points' => $this->matched_points,
            'is_proposal' => $this->isProposal(),

            // The mark of record.
            'mark' => $this->mark,
            'mark_source' => $this->mark_source,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at,
            'decided' => $this->isDecided(),
            'reason' => $this->reason,
            'feedback' => $this->feedback,

            // Flags a teacher should act on rather than discover.
            'needs_teacher' => $this->needsTeacher(),
            'needs_transcription_check' => $this->hasLowConfidenceReading(),
        ];
    }

    /**
     * The column is text holding JSON, so decoding is guarded.
     *
     * @return list<string>
     */
    private function lowConfWords(): array
    {
        $value = $this->low_confidence_words;

        if (is_array($value)) {
            return array_values(array_map('strval', $value));
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? array_values(array_map('strval', $decoded)) : [];
        }

        return [];
    }
}
