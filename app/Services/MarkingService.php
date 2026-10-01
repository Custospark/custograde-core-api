<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\MarkEvent;
use App\Models\Script;
use App\Models\ScriptAnswer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Where a human approves, changes and locks marks (REV-01 to REV-13, BR-02, BR-05).
 *
 * This is the only place a `mark` is ever written. The pipeline proposes; this
 * service decides. Three rules are enforced here rather than trusted to
 * convention:
 *
 * 1. A mark needs a person. `approved_by` is written in the same transaction as
 *    the mark, and an answer with a mark but no approver does not count as
 *    decided, so a bug elsewhere cannot manufacture a finalised result.
 * 2. Every change is recorded. Each write appends a MarkEvent with the previous
 *    value, the new value, the actor and a reason, and totals are recomputed in
 *    the same transaction (REV-13, MRK-01).
 * 3. A lock is refused until the work is genuinely done, and states exactly what
 *    is blocking rather than returning a generic failure (REV-08, MRK-02).
 */
class MarkingService
{
    /** How far a mark may move before REV-07 demands a written reason. */
    public const VARIANCE_THRESHOLD = 1.0;

    /**
     * Approve, adjust or reject one answer.
     *
     * @param  array<string, mixed>  $data  value, feedback, reason
     */
    public function decide(User $actor, ScriptAnswer $answer, array $data): ScriptAnswer
    {
        $this->assertScriptIsOpen($answer->script);

        $value = (float) $data['value'];
        $maxMark = (float) ($answer->question?->max_mark ?? $answer->suggested_mark ?? 0);

        if ($maxMark <= 0) {
            throw ValidationException::withMessages([
                'value' => 'This question has no maximum mark set, so a mark cannot be recorded. Check the paper.',
            ]);
        }

        if ($value < 0 || $value > $maxMark) {
            throw ValidationException::withMessages([
                'value' => sprintf(
                    'A mark for this question has to be between 0 and %s, and %s is not. Please check the paper.',
                    rtrim(rtrim(number_format($maxMark, 2, '.', ''), '0'), '.'),
                    rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.')
                ),
            ]);
        }

        $granularity = (float) ($answer->question?->granularity ?? 1);
        if ($granularity > 0 && fmod($value, $granularity) > 0.0001) {
            throw ValidationException::withMessages([
                'value' => sprintf(
                    'This question is marked in steps of %s, so a mark of %s is not allowed. Please use a valid step.',
                    rtrim(rtrim(number_format($granularity, 2, '.', ''), '0'), '.'),
                    rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.')
                ),
            ]);
        }

        $previous = $answer->mark;
        $reason = trim((string) ($data['reason'] ?? ''));

        // REV-07: changing a mark that already exists needs a reason. This is
        // the difference between a defensible result and an unexplained one.
        if ($previous !== null && abs((float) $previous - $value) > self::VARIANCE_THRESHOLD && $reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'This mark is being changed, so please say why. The reason is kept with the mark and shown if the result is ever questioned.',
            ]);
        }

        $source = $this->resolveSource($answer, $value);

        DB::transaction(function () use ($answer, $actor, $value, $previous, $source, $reason, $data): void {
            $answer->mark = $value;
            $answer->mark_source = $source;
            $answer->approved_by = $actor->id;
            $answer->approved_at = now();

            if ($reason !== '') {
                $answer->reason = $reason;
            }

            if (array_key_exists('feedback', $data)) {
                $answer->feedback = $data['feedback'] === null ? null : (string) $data['feedback'];
            }

            $answer->save();

            // REV-13: append-only history, written in the same transaction so
            // the trail can never disagree with the mark it describes.
            MarkEvent::query()->create([
                'script_answer_id' => $answer->id,
                'script_id' => $answer->script_id,
                'previous_value' => $previous,
                'new_value' => $value,
                'source' => $source,
                'reason' => $reason !== '' ? $reason : null,
                'actor_id' => $actor->id,
                'created_at' => now(),
            ]);

            $this->recomputeTotals($answer->script);
            $this->refreshScriptStatus($answer->script);
        });

        return $answer->refresh();
    }

    /**
     * Correct the transcription itself (OCR-04).
     *
     * The machine reading is kept beside the correction rather than replaced,
     * so a disputed result can always show what the system saw and what the
     * teacher changed.
     *
     * @param  array<string, mixed>  $data
     */
    public function correctTranscription(User $actor, ScriptAnswer $answer, array $data): ScriptAnswer
    {
        $this->assertScriptIsOpen($answer->script);

        $answer->corrected_text = $data['corrected_text'] === null
            ? null
            : trim((string) $data['corrected_text']);

        // A teacher who has read the answer themselves has removed the
        // uncertainty, whatever the model reported.
        $answer->transcription_confidence = 1.0;
        $answer->low_confidence_words = null;
        $answer->save();

        MarkEvent::query()->create([
            'script_answer_id' => $answer->id,
            'script_id' => $answer->script_id,
            'previous_value' => $answer->mark,
            'new_value' => $answer->mark,
            'source' => 'transcription_corrected',
            'reason' => 'The teacher corrected what the machine read.',
            'actor_id' => $actor->id,
            'created_at' => now(),
        ]);

        return $answer->refresh();
    }

    /**
     * Approve and lock a script (REV-08).
     *
     * The gate is checked in the order that produces the most useful message,
     * because a teacher blocked on four separate failures at once learns
     * nothing about what to do next.
     */
    public function lock(User $actor, Script $script): Script
    {
        if ($script->isLocked()) {
            return $script;
        }

        if ($script->isFlagged()) {
            throw ValidationException::withMessages([
                'script' => 'This script is flagged for investigation, so it cannot be approved yet. Clear the flag first.',
            ]);
        }

        $answers = $script->answers()->orderBy('question_number')->get();

        if ($answers->isEmpty()) {
            throw ValidationException::withMessages([
                'script' => 'Nothing has been read on this script yet, so there is nothing to approve. Check that it has been processed.',
            ]);
        }

        $missingPages = $script->expected_page_count - $script->page_count;
        if ($missingPages > 0) {
            throw ValidationException::withMessages([
                'script' => sprintf(
                    'This script is missing %d page%s, so its mark would be incomplete. Find the missing page%s before approving.',
                    $missingPages,
                    $missingPages === 1 ? '' : 's',
                    $missingPages === 1 ? '' : 's'
                ),
            ]);
        }

        // Every question must be either marked or explicitly recorded as blank.
        // A non-text answer still needs a mark: a graph cannot be graded by the
        // model, but a teacher can grade it, and leaving it out would silently
        // understate the candidate.
        $undecided = $answers->filter(fn (ScriptAnswer $answer): bool => ! $answer->isDecided());

        if ($undecided->isNotEmpty()) {
            $numbers = $undecided->pluck('question_number')->map(fn ($n): string => (string) $n)->all();

            throw ValidationException::withMessages([
                'script' => sprintf(
                    'Question%s %s still %s no mark. Mark %s before approving this script.',
                    count($numbers) === 1 ? '' : 's',
                    implode(', ', $numbers),
                    count($numbers) === 1 ? 'has' : 'have',
                    count($numbers) === 1 ? 'it' : 'them'
                ),
            ]);
        }

        DB::transaction(function () use ($script, $actor): void {
            $script->status = Script::STATUS_LOCKED;
            $script->locked_by = $actor->id;
            $script->locked_at = now();
            $script->save();

            $this->recomputeTotals($script);
        });

        Log::info('Script approved and locked', [
            'script_id' => $script->id,
            'approved_by' => $actor->id,
            'total' => $script->refresh()->total_mark,
        ]);

        return $script->refresh();
    }

    /**
     * Unlock a locked script, which only a senior role may do and only with a
     * reason (REV-08).
     */
    public function unlock(User $actor, Script $script, string $reason): Script
    {
        if (! $script->isLocked()) {
            return $script;
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'Please say why this script is being reopened, so the change is traceable.',
            ]);
        }

        DB::transaction(function () use ($script, $actor, $reason): void {
            $script->status = Script::STATUS_IN_REVIEW;
            $script->locked_by = null;
            $script->locked_at = null;
            $script->save();

            MarkEvent::query()->create([
                'script_answer_id' => $script->answers()->value('id') ?? 0,
                'script_id' => $script->id,
                'previous_value' => $script->total_mark,
                'new_value' => $script->total_mark,
                'source' => 'script_unlocked',
                'reason' => $reason,
                'actor_id' => $actor->id,
                'created_at' => now(),
            ]);
        });

        return $script->refresh();
    }

    /**
     * Flag for investigation (REV-14).
     */
    public function flag(User $actor, Script $script, string $note): Script
    {
        $script->update([
            'status' => Script::STATUS_FLAGGED,
            'flagged_by' => $actor->id,
            'flag_note' => trim($note) !== '' ? trim($note) : 'Flagged for investigation.',
        ]);

        return $script->refresh();
    }

    /**
     * BR-05: how the mark was reached.
     */
    private function resolveSource(ScriptAnswer $answer, float $value): string
    {
        $proposal = $answer->suggested_mark;

        if ($proposal === null) {
            return ScriptAnswer::SOURCE_MANUAL;
        }

        if (abs((float) $proposal - $value) < 0.0001) {
            return ScriptAnswer::SOURCE_ACCEPTED;
        }

        if ($answer->mark !== null) {
            return ScriptAnswer::SOURCE_ADJUSTED;
        }

        return ScriptAnswer::SOURCE_REJECTED;
    }

    /**
     * MRK-01: totals are computed, never typed.
     */
    private function recomputeTotals(Script $script): void
    {
        $answers = $script->answers()->get(['question_number', 'mark', 'question_id']);

        $total = 0.0;
        $max = 0.0;

        foreach ($answers as $answer) {
            $total += (float) ($answer->mark ?? 0);
            $max += (float) ($answer->question?->max_mark ?? 0);
        }

        $script->total_mark = round($total, 2);
        $script->max_mark = round($max, 2);
        $script->save();
    }

    /**
     * The status follows the answers rather than being set by hand, so it can
     * never claim a script is finished when it is not.
     */
    private function refreshScriptStatus(Script $script): void
    {
        if ($script->isLocked() || $script->isFlagged()) {
            return;
        }

        $status = $script->isComplete()
            ? Script::STATUS_READY_TO_LOCK
            : Script::STATUS_IN_REVIEW;

        if ($script->status !== $status) {
            $script->update(['status' => $status]);
        }
    }

    private function assertScriptIsOpen(Script $script): void
    {
        if ($script->isLocked()) {
            throw ValidationException::withMessages([
                'script' => 'This script has already been approved, so its marks cannot be changed. Ask an administrator to reopen it.',
            ]);
        }
    }
}
