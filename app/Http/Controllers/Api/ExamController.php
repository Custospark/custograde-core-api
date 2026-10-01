<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SetExamStatusRequest;
use App\Http\Requests\StoreExamQuestionRequest;
use App\Http\Requests\StoreExamRequest;
use App\Http\Requests\UpdateExamQuestionRequest;
use App\Http\Requests\UpdateExamRequest;
use App\Http\Resources\ExamCollection;
use App\Http\Resources\ExamQuestionResource;
use App\Http\Resources\ExamResource;
use App\Models\Exam;
use App\Models\User;
use App\Services\Contracts\ExamServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ExamController extends Controller
{
    public function __construct(
        protected ExamServiceInterface $exams,
    ) {}

    /**
     * Every paper visible to this user. Pass course_unit_id to narrow to one
     * course, or with_questions=1 when the paper builder needs the questions in
     * the same round trip. Questions are left out of the default list because a
     * teacher's working list is twenty papers, not twenty full papers.
     *
     * @return array<string, mixed>
     */
    public function index(Request $request): ExamCollection
    {
        $courseUnitId = $request->integer('course_unit_id') ?: null;

        $exams = $this->exams->list($request->user(), $courseUnitId);

        return new ExamCollection(
            $request->boolean('with_questions') ? $exams->load('questions') : $exams
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function store(StoreExamRequest $request): JsonResponse
    {
        $exam = $this->exams->create($request->user(), $request->validated());

        return response()->json(new ExamResource($exam), 201);
    }

    /**
     * @return array<string, mixed>
     */
    public function show(Request $request, string $id): ExamResource|JsonResponse
    {
        $exam = $this->findOrRefuse((int) $id, $request->user());

        return $exam instanceof Exam ? new ExamResource($exam) : $exam;
    }

    /**
     * @return array<string, mixed>
     */
    public function update(UpdateExamRequest $request, string $id): ExamResource|JsonResponse
    {
        $exam = $this->findOrRefuse((int) $id, $request->user());

        if (! $exam instanceof Exam) {
            return $exam;
        }

        $updated = $this->exams->update($request->user(), $exam, $request->validated());

        return new ExamResource($updated);
    }

    /**
     * @return array<string, mixed>
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $exam = $this->findOrRefuse((int) $id, $request->user());

        if (! $exam instanceof Exam) {
            return $exam;
        }

        // The service refuses while scripts exist, because a paper with marks on
        // it must be archived rather than removed.
        $this->exams->delete($request->user(), $exam);

        return response()->json([
            'message' => sprintf('The paper "%s" has been deleted.', $exam->title),
        ]);
    }

    /**
     * Adding a question recalculates the paper total, so the returned exam
     * already quotes the new sum.
     *
     * @return array<string, mixed>
     */
    public function addQuestion(StoreExamQuestionRequest $request, string $id): JsonResponse
    {
        $exam = $this->findOrRefuse((int) $id, $request->user());

        if (! $exam instanceof Exam) {
            return $exam;
        }

        $question = $this->exams->addQuestion($request->user(), $exam, $request->validated());

        return response()->json(new ExamQuestionResource($question), 201);
    }

    /**
     * @return array<string, mixed>
     */
    public function updateQuestion(UpdateExamQuestionRequest $request, string $id, string $question): ExamQuestionResource|JsonResponse
    {
        $exam = $this->findOrRefuse((int) $id, $request->user());

        if (! $exam instanceof Exam) {
            return $exam;
        }

        $updated = $this->exams->updateQuestion(
            $request->user(),
            $exam,
            (int) $question,
            $request->validated(),
        );

        return new ExamQuestionResource($updated);
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteQuestion(Request $request, string $id, string $question): JsonResponse
    {
        $exam = $this->findOrRefuse((int) $id, $request->user());

        if (! $exam instanceof Exam) {
            return $exam;
        }

        $this->exams->deleteQuestion($request->user(), $exam, (int) $question);

        return response()->json([
            'message' => sprintf('That question has been removed from "%s".', $exam->title),
            'exam' => new ExamResource($this->exams->recalculateTotals($exam)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function setStatus(SetExamStatusRequest $request, string $id): ExamResource|JsonResponse
    {
        $exam = $this->findOrRefuse((int) $id, $request->user());

        if (! $exam instanceof Exam) {
            return $exam;
        }

        $moved = $this->exams->setStatus($request->user(), $exam, $request->validated()['status']);

        return new ExamResource($moved);
    }

    /**
     * Recomputes the paper total and question count from its questions. Exposed
     * because an import or a bulk edit can leave the denormalised columns stale,
     * and a teacher should be able to put them right without a database job.
     *
     * @return array<string, mixed>
     */
    public function recalculateTotals(Request $request, string $id): ExamResource|JsonResponse
    {
        $exam = $this->findOrRefuse((int) $id, $request->user());

        if (! $exam instanceof Exam) {
            return $exam;
        }

        return new ExamResource($this->exams->recalculateTotals($exam));
    }

    /**
     * A paper the caller cannot see is reported exactly like one that does not
     * exist, so another school's papers cannot be probed by id.
     */
    private function findOrRefuse(int $id, User $user): Exam|JsonResponse
    {
        if ($id <= 0) {
            return response()->json(['message' => 'We could not find that examination.'], 404);
        }

        try {
            return $this->exams->find($id, $user);
        } catch (ValidationException $exception) {
            if ($exception->validator->errors()->has('id')) {
                return response()->json(['message' => 'We could not find that examination.'], 404);
            }

            throw $exception;
        }
    }
}