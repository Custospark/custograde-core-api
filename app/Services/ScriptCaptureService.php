<?php

namespace App\Services;

use App\Jobs\ProcessScriptJob;
use App\Models\Exam;
use App\Models\Script;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Capturing a scanned script (CAP-01, CAP-07, CAP-08, IDN-02).
 *
 * Two things matter here beyond storing a file.
 *
 * The original is written once and hashed (CAP-07, ARC-05). Nothing in the
 * application ever writes back over it, which is what makes it evidence.
 *
 * A capture is attributed to a person and a moment, so a paper that later turns
 * out to belong to someone else can be traced. For now the student is chosen by
 * a script with no student is kept and flagged rather than refused, because
 * refusing it would lose the paper entirely, which is precisely how scripts go
 * missing (IDN-05).
 *
 * The candidate is normally not chosen by the operator at all. The code on the
 * issued sheet is read off the scan (IDN-02) and the paper attaches to the row
 * that sheet created. When the code cannot be read the operator can still pick,
 * and if neither happens the paper is kept for the exception queue.
 */
class ScriptCaptureService
{
    /**
     * CAP-01 and CAP-06. A4 at 200 dpi is roughly 1654 by 2339 pixels, so 10 MB
     * is generous for a single page while still refusing a phone video.
     */
    public const MAX_UPLOAD_KILOBYTES = 10240;

    /**
     * @var list<string>
     */
    public const ACCEPTED_MIME_TYPES = [
        'image/png',
        'image/jpeg',
        'image/jpg',
        'image/webp',
        'application/pdf',
    ];

    public function __construct(
        private readonly string $disk = 'local',
    ) {}

    /**
     * Store a scan and queue it for reading.
     *
     * @param  array{student_id?: int|null, expected_page_count?: int|null, page_number?: int}  $meta
     */
    public function capture(User $actor, Exam $exam, UploadedFile $file, array $meta = []): Script
    {
        if (! $exam->canAcceptScripts()) {
            throw ValidationException::withMessages([
                'exam' => 'This examination has been finalised, so no more scripts can be added to it. Create a new examination instead.',
            ]);
        }

        $mime = $file->getMimeType();

        if (! in_array($mime, self::ACCEPTED_MIME_TYPES, true)) {
            throw ValidationException::withMessages([
                'file' => 'We cannot read that file as a scanned script. Please upload a photograph or a scan in PNG, JPG or PDF format.',
            ]);
        }

        $studentId = $this->resolveStudent($exam, $meta['student_id'] ?? null);

        // SHT-02: an opaque code. Nothing about the candidate is encoded in it,
        // so a lost or photographed sheet cannot expose a name.
        $code = $this->generateCode($exam);

        $extension = $this->extensionFor($file, $mime);
        $directory = sprintf('scripts/%d/%d', $exam->id, $exam->id);
        $path = sprintf('%s/%s.%s', $directory, $code, $extension);

        $storage = Storage::disk($this->disk);
        $bytes = $file->get();

        // CAP-07: written once. The hash is tamper evidence verified on a
        // schedule by the archive work in Phase 10.
        $hash = hash('sha256', $bytes);
        $storage->put($path, $bytes);

        $expectedPages = max(1, (int) ($meta['expected_page_count'] ?? 1));

        /*
         * IDN-02: read the code off the page and attach the scan to the sheet it
         * came from.
         *
         * This has to happen before a row is created, because issuing a sheet
         * already made one. Identifying afterwards would leave two rows claiming
         * one paper, and results would have no way to choose between them.
         *
         * The quick single-attempt search is used deliberately. A full search
         * costs up to eighteen seconds on a page where nothing reads, which is
         * not something to make an operator sit through on upload. Anything it
         * misses keeps its placeholder row and gets the deeper queued search.
         */
        $identified = $this->identify($storage->path($path), $mime);
        $issued = $identified === null
            ? null
            : $this->issuedScriptFor($exam, $identified['code']);

        try {
            $script = $issued !== null
                // The paper belongs to a sheet we issued. Fill that row in rather
                // than creating a second one, and keep the candidate it was
                // issued to, which is the whole point of having issued it.
                ? $this->attachToIssued($issued, [
                    'student_id' => $issued->student_id ?? $studentId,
                    'path' => $path,
                    'hash' => $hash,
                    'name' => $file->getClientOriginalName(),
                    'mime' => $mime,
                    'bytes' => strlen($bytes),
                    'pages' => max(1, (int) ($meta['page_number'] ?? 1)),
                    'expected_pages' => $identified['total'] ?? $expectedPages,
                    'actor' => $actor,
                ])
                : DB::transaction(fn (): Script => Script::query()->create([
                    'institution_id' => $exam->institution_id,
                    'owner_user_id' => $exam->owner_user_id,
                    'exam_id' => $exam->id,
                    'student_id' => $studentId,
                    'code' => $code,
                    'status' => Script::STATUS_UPLOADED,
                    'original_disk' => $this->disk,
                    'original_path' => $path,
                    'original_hash' => $hash,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $mime,
                    'size_bytes' => strlen($bytes),
                    'page_count' => max(1, (int) ($meta['page_number'] ?? 1)),
                    'expected_page_count' => $expectedPages,
                    // MRK-01: the denominator comes from the paper, not from the
                    // marks, so a teacher can see "0 of 13" from the moment the
                    // script is captured. Leaving it at zero until the first mark
                    // was decided made an untouched paper look like an empty one.
                    'total_mark' => 0,
                    'max_mark' => $this->paperMaxMark($exam),
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]));
        } catch (\Throwable $exception) {
            // Do not leave an orphan file behind if the row could not be written.
            $storage->delete($path);

            throw $exception;
        }

        // A PDF may hold several pages. Counted here rather than assumed, so a
        // multi-page upload does not silently look like a single page.
        if ($mime === 'application/pdf') {
            $pageCount = $this->countPdfPages($bytes);
            if ($pageCount > 1) {
                $script->update(['page_count' => $pageCount, 'expected_page_count' => $pageCount]);
            }
        }

        if ($studentId === null) {
            // IDN-05: the paper is kept and marked as needing identification.
            // Refusing the upload would lose it, which is how a candidate ends
            // up with no result at all.
            $script->update([
                'status' => Script::STATUS_UPLOADED,
                'flag_note' => 'This script was uploaded without a candidate, so it needs to be matched to one before it can be marked.',
            ]);
        }

        // Queued rather than run inline: reading a page takes 13 seconds and
        // grading it 10 to 25, so holding a request open is not viable.
        ProcessScriptJob::dispatch($script->id);

        Log::info('Script captured', [
            'script_id' => $script->id,
            'exam_id' => $exam->id,
            'student_id' => $studentId,
            'bytes' => $script->size_bytes,
        ]);

        return $script->refresh();
    }

    /**
     * @return array<int, Script>
     */
    public function listForExam(Exam $exam): array
    {
        return $exam->scripts()
            ->with('student')
            ->orderByDesc('created_at')
            ->get()
            ->all();
    }

    public function find(int $id, User $actor): ?Script
    {
        return Script::query()
            ->visibleTo($actor)
            ->with(['student', 'exam.courseUnit', 'answers.question'])
            ->whereKey($id)
            ->first();
    }

    /**
     * The original scan, as a temporary URL.
     *
     * The storage path is never returned by any resource. A signed, expiring URL
     * is what keeps an unpublished examination from being readable by anyone
     * who guesses a path (ARC-04).
     */
    public function temporaryUrl(Script $script): string
    {
        return Storage::disk($script->original_disk)->temporaryUrl(
            $script->original_path,
            now()->addMinutes(15),
        );
    }

    /**
     * A student from this tenant, or null when the operator could not say.
     */
    /**
     * Read the code off a stored scan. Any failure means "unidentified", which
     * is an ordinary outcome and never an error worth raising here.
     *
     * @return array{code: string, page: int, total: int}|null
     */
    private function identify(string $absolutePath, string $mime): ?array
    {
        try {
            return app(ScanIdentifier::class)->identifyFromPath($absolutePath, $mime);
        } catch (\Throwable $exception) {
            Log::warning('Scan identification failed unexpectedly', [
                'reason' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * The issued sheet row for a scanned code, if this examination issued one.
     *
     * Scoped to the examination and to codes we actually issued with a live
     * sheet, so a signature-valid code from another paper or a superseded one
     * cannot attach a scan to the wrong script.
     */
    private function issuedScriptFor(Exam $exam, string $code): ?Script
    {
        return Script::query()
            ->where('exam_id', $exam->id)
            ->where('code', $code)
            ->whereNull('original_path')
            ->whereHas('sheets', fn ($query) => $query->whereNull('invalidated_at'))
            ->first();
    }

    /**
     * Fill in a sheet that was issued ahead of the scan arriving.
     *
     * @param  array{student_id: ?int, path: string, hash: string, name: string, mime: string, bytes: int, pages: int, expected_pages: int, actor: User}  $scan
     */
    private function attachToIssued(Script $script, array $scan): Script
    {
        return DB::transaction(function () use ($script, $scan): Script {
            $script->forceFill([
                'student_id' => $scan['student_id'],
                'original_disk' => $this->disk,
                'original_path' => $scan['path'],
                'original_hash' => $scan['hash'],
                'original_name' => $scan['name'],
                'mime_type' => $scan['mime'],
                'size_bytes' => $scan['bytes'],
                'page_count' => $scan['pages'],
                'expected_page_count' => $scan['expected_pages'],
                'status' => Script::STATUS_UPLOADED,
                'total_mark' => 0,
                'max_mark' => $this->paperMaxMark($script->exam),
                'created_by' => $script->created_by ?? $scan['actor']->id,
                'updated_by' => $scan['actor']->id,
                // The sheet was issued and then the paper arrived, so there is
                // nothing left to explain to a marker.
                'flag_note' => null,
            ])->save();

            return $script->refresh();
        });
    }

    private function resolveStudent(Exam $exam, ?int $studentId): ?int
    {
        if ($studentId === null) {
            return null;
        }

        $enrolled = $exam->students()->whereKey($studentId)->exists();

        if (! $enrolled) {
            throw ValidationException::withMessages([
                'student_id' => 'That candidate is not on the list for this examination. Enrol them first, or upload the script without a candidate so it can be matched later.',
            ]);
        }

        return $studentId;
    }

    /**
     * The total the paper can award, so progress is readable before any mark
     * exists. Recomputed on capture rather than trusted, because a question can
     * be added or removed between two scans of the same paper.
     */
    private function paperMaxMark(Exam $exam): float
    {
        $total = (float) $exam->questions()->sum('max_mark');

        $exam->update([
            'total_marks' => (int) round($total),
            'question_count' => $exam->questions()->count(),
        ]);

        return round($total, 2);
    }

    /**
     * SHT-02: opaque and unique, carrying no personal data.
     */
    private function generateCode(Exam $exam): string
    {
        do {
            $code = sprintf('CG-%05d-%s', $exam->id, strtoupper(bin2hex(random_bytes(3))));
        } while (Script::query()->where('code', $code)->exists());

        return $code;
    }

    private function extensionFor(UploadedFile $file, string $mime): string
    {
        return match ($mime) {
            'image/png' => 'png',
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            default => $file->guessExtension() ?? 'bin',
        };
    }

    /**
     * Count pages in a PDF by counting page objects, without a PDF library.
     *
     * An approximate count is enough here. What matters is not understating a
     * multi-page upload, because a script that looks like one page when it is
     * three is exactly the incompleteness MRK-02 has to catch later.
     */
    private function countPdfPages(string $bytes): int
    {
        $matches = [];

        if (preg_match_all('#/Type\s*/Page[^s]#', $bytes, $matches) === false) {
            return 1;
        }

        $count = count($matches[0]);

        return $count > 0 ? $count : 1;
    }
}
