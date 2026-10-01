<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessScriptJob;
use App\Http\Requests\CaptureScriptRequest;
use App\Http\Requests\DecideMarkRequest;
use App\Http\Requests\CorrectTranscriptionRequest;
use App\Http\Requests\LockScriptRequest;
use App\Http\Requests\FlagScriptRequest;
use App\Http\Resources\ScriptResource;
use App\Http\Resources\ScriptAnswerResource;
use App\Models\Exam;
use App\Models\Script;
use App\Models\ScriptAnswer;
use App\Services\MarkingService;
use App\Services\ScriptCaptureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Scripts and the marking workspace (CAP, OCR, REV-01 to REV-13).
 */
class ScriptController extends Controller
{
    public function __construct(
        protected ScriptCaptureService $capture,
        protected MarkingService $marking,
    ) {}

    /**
     * Every script on an examination, newest first.
     */
    public function index(Request $request, int $examId): JsonResponse
    {
        $exam = $this->findExamOrRefuse($examId, $request);

        if (! $exam instanceof Exam) {
            return $exam;
        }

        $scripts = $this->capture->listForExam($exam);

        return response()->json([
            'scripts' => ScriptResource::collection($scripts)->resolve(),
        ]);
    }

    /**
     * One script with its answers, the shape the review workspace renders.
     *
     * STU-07: in a blind-marked examination the candidate's identity is left out
     * entirely rather than hidden in the client, so it is never sent to a
     * browser that should not have it.
     */
    public function show(Request $request, int $id): ScriptResource|JsonResponse
    {
        $script = $this->findScriptOrRefuse($id, $request);

        if (! $script instanceof Script) {
            return $script;
        }

        return new ScriptResource($script);
    }

    /**
     * The scan itself, as a short-lived signed URL (ARC-04).
     */
    public function image(Request $request, int $id): JsonResponse
    {
        $script = $this->findScriptOrRefuse($id, $request);

        if (! $script instanceof Script) {
            return $script;
        }

        try {
            $url = $this->capture->temporaryUrl($script);
        } catch (\Throwable) {
            return response()->json([
                'message' => 'The scan could not be opened just now. Please try again.',
            ], 503);
        }

        return response()->json([
            'url' => $url,
            'expires_in' => 900,
        ]);
    }

    /**
     * Upload a scanned script and start reading it.
     */
    public function store(CaptureScriptRequest $request, int $examId): JsonResponse
    {
        $exam = $this->findExamOrRefuse($examId, $request);

        if (! $exam instanceof Exam) {
            return $exam;
        }

        $script = $this->capture->capture(
            $request->user(),
            $exam,
            $request->file('file'),
            [
                'student_id' => $request->validated()['student_id'] ?? null,
                'expected_page_count' => $request->validated()['expected_page_count'] ?? null,
            ],
        );

        $warnings = [];

        if ($script->student_id === null) {
            $warnings[] = 'This script has no candidate yet, so it must be matched to one before it can be marked.';
        }

        return response()->json([
            'script' => (new ScriptResource($script))->resolve(),
            'warnings' => $warnings,
        ], 201);
    }

    /**
     * Re-run the pipeline, for a scan that was rejected or an AI outage.
     */
    public function reprocess(Request $request, int $id): JsonResponse
    {
        $script = $this->findScriptOrRefuse($id, $request);

        if (! $script instanceof Script) {
            return $script;
        }

        if ($script->isLocked()) {
            return response()->json([
                'message' => 'This script has been approved, so it cannot be read again. Ask an administrator to reopen it.',
            ], 422);
        }

        ProcessScriptJob::dispatch($script->id);

        return response()->json([
            'message' => 'This script is being read again. It usually takes about half a minute.',
        ]);
    }

    /**
     * Approve, adjust or change one mark (REV-01).
     *
     * The only route in the application that writes a mark.
     */
    public function decideMark(DecideMarkRequest $request, int $id, int $answerId): JsonResponse
    {
        $script = $this->findScriptOrRefuse($id, $request);

        if (! $script instanceof Script) {
            return $script;
        }

        $answer = ScriptAnswer::query()
            ->where('script_id', $script->id)
            ->whereKey($answerId)
            ->first();

        if ($answer === null) {
            return response()->json([
                'message' => 'We could not find that question on this script.',
            ], 404);
        }

        $updated = $this->marking->decide($request->user(), $answer, $request->validated());

        return response()->json([
            'answer' => (new ScriptAnswerResource($updated))->resolve(),
            'script' => (new ScriptResource($script->refresh()->load('answers.question')))->resolve(),
        ]);
    }

    /**
     * Correct what the machine read (OCR-04).
     */
    public function correctTranscription(
        CorrectTranscriptionRequest $request,
        int $id,
        int $answerId
    ): JsonResponse {
        $script = $this->findScriptOrRefuse($id, $request);

        if (! $script instanceof Script) {
            return $script;
        }

        $answer = ScriptAnswer::query()
            ->where('script_id', $script->id)
            ->whereKey($answerId)
            ->first();

        if ($answer === null) {
            return response()->json([
                'message' => 'We could not find that question on this script.',
            ], 404);
        }

        $updated = $this->marking->correctTranscription($request->user(), $answer, $request->validated());

        return response()->json([
            'answer' => (new ScriptAnswerResource($updated))->resolve(),
        ]);
    }

    /**
     * Approve and lock a script (REV-08).
     */
    public function lock(LockScriptRequest $request, int $id): JsonResponse
    {
        $script = $this->findScriptOrRefuse($id, $request);

        if (! $script instanceof Script) {
            return $script;
        }

        $locked = $this->marking->lock($request->user(), $script);

        return response()->json([
            'script' => (new ScriptResource($locked->load('answers.question')))->resolve(),
            'message' => 'This script is approved. The mark is now final.',
        ]);
    }

    /**
     * Reopen an approved script, with a reason (REV-08).
     */
    public function unlock(LockScriptRequest $request, int $id): JsonResponse
    {
        $script = $this->findScriptOrRefuse($id, $request);

        if (! $script instanceof Script) {
            return $script;
        }

        // A reason is not required by the Form Request because approving does
        // not need one. It is only mandatory when reopening, so a missing value
        // is passed through as an empty string and MarkingService decides.
        $reopened = $this->marking->unlock(
            $request->user(),
            $script,
            (string) ($request->validated()['reason'] ?? '')
        );

        return response()->json([
            'script' => (new ScriptResource($reopened->load('answers.question')))->resolve(),
            'message' => 'This script is open again.',
        ]);
    }

    /**
     * Flag for investigation (REV-14).
     */
    public function flag(FlagScriptRequest $request, int $id): JsonResponse
    {
        $script = $this->findScriptOrRefuse($id, $request);

        if (! $script instanceof Script) {
            return $script;
        }

        $flagged = $this->marking->flag($request->user(), $script, (string) $request->validated()['note']);

        return response()->json([
            'script' => (new ScriptResource($flagged))->resolve(),
        ]);
    }

    private function findScriptOrRefuse(int $id, Request $request): Script|JsonResponse
    {
        $script = $this->capture->find($id, $request->user());

        if ($script === null) {
            return response()->json([
                'message' => 'We could not find that script. It may have been removed, or it may belong to another school.',
            ], 404);
        }

        return $script;
    }

    private function findExamOrRefuse(int $id, Request $request): Exam|JsonResponse
    {
        $exam = Exam::query()
            ->visibleTo($request->user())
            ->whereKey($id)
            ->first();

        if ($exam === null) {
            return response()->json([
                'message' => 'We could not find that examination. It may have been removed, or it may belong to another school.',
            ], 404);
        }

        return $exam;
    }
}
