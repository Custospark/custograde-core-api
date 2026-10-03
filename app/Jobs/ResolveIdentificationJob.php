<?php

namespace App\Jobs;

use App\Models\Script;
use App\Services\ScanIdentifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * A second, deeper attempt at identifying a scan (IDN-08).
 *
 * The capture path makes one attempt on purpose, because a thorough search costs
 * up to eighteen seconds and nobody should wait that long on an upload. This job
 * is where that time is acceptable: it searches every region and every image
 * variant, so a sheet that was fed in upside down, or one from a template we did
 * not produce, still gets matched to its candidate rather than sitting in the
 * exception queue for a person to resolve by hand.
 *
 * The transfer is guarded rather than assumed. By the time this runs, the
 * placeholder row may already have answers transcribed against it. Moving a file
 * onto another row while leaving those answers behind would put a candidate's
 * marks on the wrong paper, which is the single worst thing this system could
 * do. So the placeholder is only removed when it is provably empty, and anything
 * else is escalated to a person.
 */
class ResolveIdentificationJob implements ShouldQueue
{
    use Queueable;

    /** One attempt: an unreadable paper is a human's problem, not a retry loop. */
    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public readonly int $scriptId) {}

    public function handle(ScanIdentifier $identifier): void
    {
        $script = Script::find($this->scriptId);

        if ($script === null || $script->student_id !== null || $script->original_path === null) {
            return;
        }

        $disk = Storage::disk($script->original_disk);
        $path = $disk->path($script->original_path);

        if (! $disk->exists($script->original_path)) {
            $this->escalate($script, 'The stored scan is missing, so it cannot be matched automatically.');

            return;
        }

        $decoded = $identifier->identifyFromPath($path, (string) $script->mime_type, 9, true);

        if ($decoded === null) {
            // Not an error. The paper stays, with its flag, for an officer.
            return;
        }

        $target = Script::query()
            ->where('exam_id', $script->exam_id)
            ->where('code', $decoded['code'])
            ->whereNull('original_path')
            ->whereHas('sheets', fn ($query) => $query->whereNull('invalidated_at'))
            ->first();

        if ($target === null) {
            // A code we can read but cannot place: another paper, a superseded
            // sheet, or a duplicate of one already scanned. A person decides,
            // because guessing here risks attaching marks to the wrong
            // candidate, which is the worst thing this system could do.
            $this->escalate(
                $script,
                sprintf(
                    'The code on this page reads as %s, which does not belong to any sheet still waiting for a scan on this paper. Check it by hand.',
                    $decoded['code']
                )
            );

            return;
        }

        if ($target->id === $script->id) {
            $this->attachCandidate($script, $target);

            return;
        }

        $this->transfer($script, $target);
    }

    /**
     * The scan already belongs to the right row, so only the candidate is missing.
     */
    private function attachCandidate(Script $script, Script $target): void
    {
        if ($target->student_id === null) {
            return;
        }

        $script->forceFill([
            'student_id' => $target->student_id,
            'flag_note' => null,
        ])->saveQuietly();

        Log::info('Late identification matched in place', ['script_id' => $script->id]);
    }

    /**
     * Move the scan onto the sheet it came from and drop the placeholder.
     *
     * The placeholder is only removed when it carries nothing. If answers already
     * exist against it, the paper is flagged for a person instead, because
     * deleting a row that holds marks is not a decision this job may take.
     */
    private function transfer(Script $script, Script $target): void
    {
        $hasAnswers = $script->answers()->exists();

        if ($hasAnswers) {
            $this->escalate(
                $script,
                sprintf(
                    'This page carries the code for %s, but answers were already read against it. '
                    .'A person must decide which paper these marks belong to.',
                    $target->student?->full_name ?? 'another candidate'
                )
            );

            return;
        }

        DB::transaction(function () use ($script, $target): void {
            $target->forceFill([
                'student_id' => $target->student_id ?? $script->student_id,
                'original_disk' => $script->original_disk,
                'original_path' => $script->original_path,
                'original_hash' => $script->original_hash,
                'original_name' => $script->original_name,
                'mime_type' => $script->mime_type,
                'size_bytes' => $script->size_bytes,
                'page_count' => $script->page_count,
                'expected_page_count' => $script->expected_page_count ?: $script->page_count,
                'status' => Script::STATUS_UPLOADED,
                'flag_note' => null,
                'updated_by' => $script->updated_by,
            ])->save();

            $script->delete();
        });

        // The pipeline never ran for the placeholder, so the real row now needs it.
        ProcessScriptJob::dispatch($target->id);

        Log::info('Late identification transferred a scan', [
            'placeholder' => $script->id,
            'script_id' => $target->id,
        ]);
    }

    /**
     * Hand the paper to a person, keeping every mark that exists.
     */
    private function escalate(Script $script, string $reason): void
    {
        $script->forceFill([
            'flag_note' => $reason,
            'status' => Script::STATUS_FLAGGED,
        ])->saveQuietly();

        Log::info('Identification escalated to a person', [
            'script_id' => $script->id,
            'reason' => $reason,
        ]);
    }
}