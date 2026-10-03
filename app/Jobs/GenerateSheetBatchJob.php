<?php

namespace App\Jobs;

use App\Models\Exam;
use App\Models\Script;
use App\Models\ScriptSheet;
use App\Models\SheetBatch;
use App\Models\Student;
use App\Services\AnswerSheetService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Prints a whole class of answer sheets into one archive (SHT-05).
 *
 * Queued because rendering one A4 sheet with a QR costs a second or so, so thirty
 * candidates is half a minute of CPU that does not belong in a web request.
 *
 * Three decisions in here are load bearing.
 *
 * A candidate who already has a current sheet is SKIPPED, not reissued. Running
 * "print the class" twice must not quietly rotate thirty live codes, which would
 * invalidate every sheet already printed and sitting in a box at the school.
 *
 * A failure on one candidate does not abandon the rest. The name and reason are
 * collected and the batch completes, because a class where twenty-nine of thirty
 * sheets printed is far more useful than an error page and nothing at all.
 *
 * The archive path is only written once every candidate has been dealt with. A
 * partially written ZIP would download and open cleanly, and a teacher would
 * print it believing the class was covered.
 */
class GenerateSheetBatchJob implements ShouldQueue
{
    use Queueable;

    /** One attempt: a candidate that fails is recorded, not retried forever. */
    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public readonly int $batchId) {}

    public function handle(AnswerSheetService $sheets): void
    {
        $batch = SheetBatch::find($this->batchId);

        if ($batch === null || $batch->isFinished()) {
            return;
        }

        $batch->update(['status' => SheetBatch::STATUS_RUNNING, 'started_at' => now()]);

        $exam = Exam::findOrFail($batch->exam_id);
        $candidates = $this->enrolledCandidates($exam);

        $batch->update(['total' => $candidates->count()]);

        $archivePath = sprintf('sheet-batches/%d/%d.zip', $exam->id, $batch->id);
        $temporary = tempnam(sys_get_temp_dir(), 'sheets').'.zip';

        $zip = new ZipArchive;
        $opened = $zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($opened !== true) {
            $batch->update([
                'status' => SheetBatch::STATUS_FAILED,
                'errors' => [['candidate' => 'batch', 'reason' => 'The archive could not be created on the server.']],
                'finished_at' => now(),
            ]);

            return;
        }

        $processed = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        foreach ($candidates as $student) {
            try {
                $script = $this->currentScriptFor($exam, $student);

                if ($script !== null) {
                    $skipped++;
                    $batch->update(['skipped' => $skipped]);

                    continue;
                }

                $script = $this->issueSheet($exam, $student, $sheets);
                $zip->addFromString($this->entryName($script), $sheets->render($script));
                $processed++;
            } catch (\Throwable $exception) {
                // Isolated on purpose: one bad candidate must not cost the class.
                $failed++;
                $errors[] = [
                    'candidate' => trim($student->first_name.' '.$student->last_name),
                    'reason' => 'This candidate could not be given a sheet. Issue one for them individually.',
                ];
                Log::warning('Answer sheet generation failed for a candidate', [
                    'batch_id' => $batch->id,
                    'student_id' => $student->id,
                    'reason' => $exception->getMessage(),
                ]);
            }

            // Written every candidate rather than at the end, so a batch that
            // dies still shows how far it got.
            $batch->update([
                'processed' => $processed,
                'skipped' => $skipped,
                'failed' => $failed,
                'errors' => $errors ?: null,
            ]);
        }

        $zip->close();

        // An archive with no entries is not a useful download, and ZipArchive
        // does not reliably materialise a file in that case, so reading it would
        // emit a warning and store `false`. Only publish an archive that exists.
        $archive = null;
        $bytes = null;

        if ($processed > 0 && is_file($temporary) && filesize($temporary) > 0) {
            $contents = file_get_contents($temporary);

            if ($contents !== false) {
                $stored = Storage::disk($batch->disk)->put($archivePath, $contents);

                if ($stored !== false) {
                    $archive = $archivePath;
                    $bytes = Storage::disk($batch->disk)->size($archivePath);
                }
            }
        }

        @unlink($temporary);

        $batch->update([
            'status' => $archive !== null ? SheetBatch::STATUS_COMPLETED : SheetBatch::STATUS_FAILED,
            'processed' => $processed,
            'skipped' => $skipped,
            'failed' => $failed,
            'errors' => $errors ?: ($archive === null && $skipped === 0
                ? [['candidate' => 'batch', 'reason' => 'No candidates were enrolled for this paper, so no sheets were printed.']]
                : null),
            'archive_path' => $archive,
            'archive_bytes' => $bytes,
            'finished_at' => now(),
        ]);
    }

    /**
     * The script row for a candidate who already has a live sheet, or null.
     *
     * Scoped to the examination as well as the student, because a candidate can
     * sit several papers and only this paper's sheet matters here.
     */
    private function currentScriptFor(Exam $exam, Student $student): ?Script
    {
        return Script::query()
            ->where('exam_id', $exam->id)
            ->where('student_id', $student->id)
            ->where('status', Script::STATUS_ISSUED)
            ->whereHas('sheets', fn ($query) => $query->whereNull('invalidated_at'))
            ->first();
    }

    /**
     * Create the script and its sheet, mirroring what the single-sheet endpoint
     * does. Duplicated rather than shared because the batch has no authenticated
     * user per candidate and the controller path is request shaped.
     */
    private function issueSheet(Exam $exam, Student $student, AnswerSheetService $sheets): Script
    {
        return DB::transaction(function () use ($exam, $student, $sheets): Script {
            $pages = max(1, (int) ceil($exam->questions()->count() / AnswerSheetService::QUESTIONS_PER_PAGE));

            $script = Script::create([
                'institution_id' => $exam->institution_id,
                'owner_user_id' => $exam->owner_user_id,
                'exam_id' => $exam->id,
                'student_id' => $student->id,
                'code' => $this->uniqueCode($exam->id),
                'status' => Script::STATUS_ISSUED,
                'expected_page_count' => $pages,
                'page_count' => 0,
            ]);

            ScriptSheet::create([
                'script_id' => $script->id,
                'institution_id' => $exam->institution_id,
                'code' => $script->code,
                'page_count' => $pages,
                'issued_at' => now(),
            ]);

            return $script;
        });
    }

    /**
     * A readable archive entry name.
     *
     * Prefixed with the registration number so sheets come off the printer in a
     * usable order and a teacher can find a candidate without opening thirty
     * files, and suffixed with the code so two candidates sharing a name cannot
     * collide in the archive.
     */
    private function entryName(Script $script): string
    {
        $student = $script->student;
        $label = $student?->reg_no ?? 'unassigned';

        return sprintf('%s-%s.pdf', $this->safe($label), $script->code);
    }

    private function safe(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9._-]/', '-', $value) ?: 'sheet';
    }

    private function uniqueCode(int $examId): string
    {
        do {
            $code = sprintf('CG-%05d-%s', $examId, strtoupper(bin2hex(random_bytes(3))));
        } while (Script::where('code', $code)->exists());

        return $code;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Student>
     */
    private function enrolledCandidates(Exam $exam)
    {
        return Student::query()
            ->where('institution_id', $exam->institution_id)
            ->whereIn('id', DB::table('exam_enrolments')->where('exam_id', $exam->id)->pluck('student_id'))
            ->orderBy('reg_no')
            ->get();
    }

    public function failed(\Throwable $exception): void
    {
        SheetBatch::whereKey($this->batchId)->update([
            'status' => SheetBatch::STATUS_FAILED,
            'errors' => [['candidate' => 'batch', 'reason' => 'Generation stopped unexpectedly. Start it again.']],
            'finished_at' => now(),
        ]);
    }
}