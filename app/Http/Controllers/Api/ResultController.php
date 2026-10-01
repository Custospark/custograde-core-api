<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ResultResource;
use App\Models\Exam;
use App\Models\Result;
use App\Services\ResultService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Compiling, grading and releasing results (MRK-01 to MRK-10).
 */
class ResultController extends Controller
{
    public function __construct(protected ResultService $results) {}

    public function index(Request $request, int $examId): JsonResponse
    {
        $exam = $this->findExamOrRefuse($examId, $request);

        if (! $exam instanceof Exam) {
            return $exam;
        }

        $query = Result::query()
            ->where('exam_id', $exam->id)
            ->with('student')
            // MRK-08: only the current version, but the version number travels
            // with each row so a client can see that a correction happened.
            ->orderBy('student_id')
            ->orderByDesc('version');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return response()->json([
            'results' => ResultResource::collection($query->get())->resolve(),
        ]);
    }

    /**
     * Class statistics (MRK-03).
     */
    public function statistics(Request $request, int $examId): JsonResponse
    {
        $exam = $this->findExamOrRefuse($examId, $request);

        if (! $exam instanceof Exam) {
            return $exam;
        }

        return response()->json(['statistics' => $this->results->statistics($exam)]);
    }

    /**
     * Compile results from the approved scripts.
     */
    public function compile(Request $request, int $examId): JsonResponse
    {
        $exam = $this->findExamOrRefuse($examId, $request);

        if (! $exam instanceof Exam) {
            return $exam;
        }

        $outcome = $this->results->compileForExam($request->user(), $exam);

        $warnings = [];

        if ($outcome['skipped'] !== []) {
            $warnings[] = sprintf(
                'These scripts could not be compiled because no candidate is attached to them: %s. '
                . 'Match them to a candidate and compile again.',
                implode(', ', $outcome['skipped'])
            );
        }

        return response()->json([
            'compiled' => $outcome['compiled'],
            'warnings' => $warnings,
            'statistics' => $this->results->statistics($exam),
        ]);
    }

    /**
     * Release to candidates (MRK-09).
     */
    public function release(Request $request, int $examId): JsonResponse
    {
        $exam = $this->findExamOrRefuse($examId, $request);

        if (! $exam instanceof Exam) {
            return $exam;
        }

        $count = $this->results->release($request->user(), $exam);

        return response()->json([
            'released' => $count,
            'message' => sprintf('Results for %d candidate%s now released.', $count, $count === 1 ? '' : 's'),
        ]);
    }

    /**
     * Withdraw a release without deleting anything.
     */
    public function withhold(Request $request, int $examId): JsonResponse
    {
        $exam = $this->findExamOrRefuse($examId, $request);

        if (! $exam instanceof Exam) {
            return $exam;
        }

        $count = $this->results->withhold($request->user(), $exam);

        return response()->json([
            'withheld' => $count,
            'message' => 'Results are no longer visible to candidates. Nothing has been deleted.',
        ]);
    }

    private function findExamOrRefuse(int $id, Request $request): Exam|JsonResponse
    {
        $exam = Exam::query()->visibleTo($request->user())->whereKey($id)->first();

        if ($exam === null) {
            return response()->json([
                'message' => 'We could not find that examination. It may have been removed, or it may belong to another school.',
            ], 404);
        }

        return $exam;
    }
}
