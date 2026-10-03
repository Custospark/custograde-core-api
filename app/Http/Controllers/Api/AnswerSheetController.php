<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Script;
use App\Models\ScriptSheet;
use App\Models\Student;
use App\Services\AnswerSheetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Issuing printable answer sheets (SHT-01 to SHT-09).
 *
 * Generating a sheet is what turns "upload one script at a time" into "mark a
 * class", because the sheet is what carries the machine-readable identity back
 * with the paper. It also creates the script row up front, which is what makes
 * automatic identification possible later: a scan's code resolves to a script
 * that already exists and already knows its candidate.
 */
class AnswerSheetController extends Controller
{
    public function __construct(
        private readonly AnswerSheetService $sheets,
    ) {}

    /**
     * Issue a sheet for one candidate (SHT-01, SHT-02).
     */
    public function store(Request $request, int $examId): JsonResponse
    {
        $exam = $this->findExamOrRefuse($examId, $request);

        if (! $exam instanceof Exam) {
            return $exam;
        }

        $data = $request->validate([
            'student_id' => ['required', 'integer'],
        ]);

        // The candidate must belong to this examination. Checking enrolment
        // rather than the student table alone stops a sheet being issued for
        // someone who was never entered for the paper, which would produce a
        // script row that no result could ever attach to.
        $enrolled = DB::table('exam_enrolments')
            ->where('exam_id', $exam->id)
            ->where('student_id', $data['student_id'])
            ->exists();

        if (! $enrolled) {
            throw ValidationException::withMessages([
                'student_id' => ['That candidate is not enrolled for this examination.'],
            ]);
        }

        $student = Student::findOrFail($data['student_id']);
        $sheet = $this->issue($exam, $student, $request);

        return response()->json([
            'sheet' => [
                'id' => $sheet->id,
                'script_id' => $sheet->script_id,
                'code' => $sheet->code,
                'page_count' => $sheet->page_count,
                'issued_at' => $sheet->issued_at->toIso8601String(),
                'download_url' => "/api/v1/exams/{$exam->id}/sheets/{$sheet->script_id}",
            ],
        ], 201);
    }

    /**
     * Issue a replacement, invalidating the earlier code (SHT-06).
     *
     * The earlier row is kept and marked invalid rather than deleted, because a
     * dispute about which sheet a candidate sat is exactly what the history is
     * for. Invalidating matters functionally too: a scan carrying a withdrawn
     * code must not silently attach to the script.
     */
    public function reissue(Request $request, int $examId, int $scriptId): JsonResponse
    {
        $exam = $this->findExamOrRefuse($examId, $request);

        if (! $exam instanceof Exam) {
            return $exam;
        }

        $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $script = $this->findScriptOrRefuse($exam, $scriptId, $request);

        if (! $script instanceof Script) {
            return $script;
        }

        if ($script->original_path !== null) {
            return response()->json([
                'message' => 'This script already has a scan attached, so a replacement sheet would be misleading. Reopen the mark instead.',
            ], 422);
        }

        $reason = $request->string('reason')->toString();

        DB::transaction(function () use ($exam, $script, $request, $reason): void {
            ScriptSheet::where('script_id', $script->id)
                ->whereNull('invalidated_at')
                ->update([
                    'invalidated_at' => now(),
                    'invalidation_reason' => $reason,
                    'invalidated_by' => $request->user()?->id,
                ]);

            $this->issueSheet($script, $this->estimatePages($exam), $request);
        });

        return response()->json([
            'message' => 'Replacement sheet issued. The earlier code no longer identifies this script.',
        ], 201);
    }

    /**
     * Download the sheet as a PDF (SHT-01, SHT-08).
     */
    public function download(Request $request, int $examId, int $scriptId): Response|JsonResponse
    {
        $exam = $this->findExamOrRefuse($examId, $request);

        if (! $exam instanceof Exam) {
            return $exam;
        }

        $script = $this->findScriptOrRefuse($exam, $scriptId, $request);

        if (! $script instanceof Script) {
            return $script;
        }

        return response($this->sheets->render($script), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->sheets->fileNameFor($script).'"',
        ]);
    }

    /**
     * Every sheet issued for an examination, with which are still current.
     */
    public function index(Request $request, int $examId): JsonResponse
    {
        $exam = $this->findExamOrRefuse($examId, $request);

        if (! $exam instanceof Exam) {
            return $exam;
        }

        $sheets = ScriptSheet::query()
            ->where('institution_id', $exam->institution_id)
            ->whereHas('script', fn ($query) => $query->where('exam_id', $exam->id))
            ->with('script.student')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ScriptSheet $sheet) => [
                'id' => $sheet->id,
                'script_id' => $sheet->script_id,
                'code' => $sheet->code,
                'page_count' => $sheet->page_count,
                'current' => $sheet->isCurrent(),
                'invalidated_at' => $sheet->invalidated_at?->toIso8601String(),
                'invalidation_reason' => $sheet->invalidation_reason,
                'candidate' => $sheet->script?->student?->full_name,
            ]);

        return response()->json(['sheets' => $sheets]);
    }

    /**
     * Create the script and its first sheet.
     *
     * Split from `issueSheet` because reissuing must reuse the existing script
     * row. Creating a second script for the same candidate would leave two rows
     * claiming one paper, and results would have no way to choose between them.
     */
    private function issue(Exam $exam, ?Student $student, Request $request): ScriptSheet
    {
        $pages = $this->estimatePages($exam);

        $script = Script::create([
            'institution_id' => $exam->institution_id,
            'owner_user_id' => $exam->owner_user_id,
            'exam_id' => $exam->id,
            'student_id' => $student?->id,
            'code' => $this->generateCode($exam->id),
            // A sheet is issued before any paper exists, so it needs a status
            // outside the marking lifecycle. `issued` means "the code is live
            // and no scan has arrived", which is not the same as `uploaded`.
            'status' => Script::STATUS_ISSUED,
            'expected_page_count' => $pages,
            'page_count' => 0,
            'created_by' => $request->user()?->id,
        ]);

        return $this->issueSheet($script, $pages, $request);
    }

    /**
     * Issue a sheet against a script that already exists, rotating its code.
     *
     * The code rotates because SHT-06 requires the earlier one to stop working.
     * Rotating the script's code rather than only the sheet row is what makes
     * that true in practice: the code is what a scan carries and what the
     * printed fallback quotes, so leaving it unchanged would leave a withdrawn
     * sheet still resolvable.
     */
    private function issueSheet(Script $script, int $pages, Request $request): ScriptSheet
    {
        $script->forceFill(['code' => $this->generateCode($script->exam_id)])->save();

        return ScriptSheet::create([
            'script_id' => $script->id,
            'institution_id' => $script->institution_id,
            'code' => $script->code,
            'page_count' => $pages,
            'issued_at' => now(),
            'issued_by' => $request->user()?->id,
        ]);
    }

    /**
     * How many pages a sheet will have, matching the renderer's own pagination
     * so the recorded count cannot disagree with the printed one.
     */
    private function estimatePages(Exam $exam): int
    {
        $questions = $exam->questions()->count();

        return max(1, (int) ceil($questions / AnswerSheetService::QUESTIONS_PER_PAGE));
    }

    private function generateCode(int $examId): string
    {
        do {
            $code = sprintf('CG-%05d-%s', $examId, strtoupper(bin2hex(random_bytes(3))));
        } while (Script::where('code', $code)->exists());

        return $code;
    }

    private function findExamOrRefuse(int $examId, Request $request): Exam|JsonResponse
    {
        $exam = Exam::query()
            ->visibleTo($request->user())
            ->find($examId);

        if (! $exam instanceof Exam) {
            return response()->json(['message' => 'We could not find that examination.'], 404);
        }

        return $exam;
    }

    private function findScriptOrRefuse(Exam $exam, int $scriptId, Request $request): Script|JsonResponse
    {
        // Scoped to this examination as well as the tenant, so a script id from
        // another paper in the same school is a 404 rather than a wrong sheet.
        $script = Script::query()
            ->visibleTo($request->user())
            ->where('exam_id', $exam->id)
            ->find($scriptId);

        if (! $script instanceof Script) {
            return response()->json(['message' => 'We could not find that script.'], 404);
        }

        return $script;
    }
}