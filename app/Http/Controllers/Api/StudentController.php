<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EnrolStudentRequest;
use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Http\Resources\ExamResource;
use App\Http\Resources\StudentCollection;
use App\Http\Resources\StudentResource;
use App\Models\Exam;
use App\Models\Student;
use App\Models\User;
use App\Services\Contracts\ExamServiceInterface;
use App\Services\Contracts\StudentServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StudentController extends Controller
{
    public function __construct(
        protected StudentServiceInterface $students,
        protected ExamServiceInterface $exams,
    ) {}

    /**
     * The roster. Pass exam_id to see who sits one paper, search to match a
     * registration number or name, or class_name for one class.
     *
     * @return array<string, mixed>
     */
    public function index(Request $request): StudentCollection
    {
        $examId = $request->integer('exam_id') ?: null;
        $search = $request->string('search')->trim()->value() ?: null;
        $className = $request->string('class_name')->trim()->value() ?: null;

        return new StudentCollection($this->students->list(
            $request->user(),
            $examId,
            $search,
            $className,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function store(StoreStudentRequest $request): JsonResponse
    {
        $student = $this->students->create($request->user(), $request->validated());

        return response()->json(new StudentResource($student), 201);
    }

    /**
     * @return array<string, mixed>
     */
    public function show(Request $request, string $id): StudentResource|JsonResponse
    {
        $student = $this->findOrRefuse((int) $id, $request->user());

        return $student instanceof Student ? new StudentResource($student) : $student;
    }

    /**
     * @return array<string, mixed>
     */
    public function update(UpdateStudentRequest $request, string $id): StudentResource|JsonResponse
    {
        $student = $this->findOrRefuse((int) $id, $request->user());

        if (! $student instanceof Student) {
            return $student;
        }

        $updated = $this->students->update($request->user(), $student, $request->validated());

        return new StudentResource($updated);
    }

    /**
     * @return array<string, mixed>
     */
    public function enrol(EnrolStudentRequest $request, string $id): JsonResponse
    {
        $exam = $this->findExamOrRefuse((int) $id, $request->user());

        if (! $exam instanceof Exam) {
            return $exam;
        }

        $studentId = (int) $request->validated()['student_id'];

        $this->students->enrol($request->user(), $exam, $studentId);

        return response()->json([
            'message' => sprintf('The student has been entered for "%s".', $exam->title),
            'exam' => new ExamResource($exam->loadCount('students')),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    public function unenrol(Request $request, string $id, string $student): JsonResponse
    {
        $exam = $this->findExamOrRefuse((int) $id, $request->user());

        if (! $exam instanceof Exam) {
            return $exam;
        }

        $this->students->unenrol($request->user(), $exam, (int) $student);

        return response()->json([
            'message' => sprintf('The student has been removed from "%s".', $exam->title),
            'exam' => new ExamResource($exam->loadCount('students')),
        ]);
    }

    /**
     * A candidate the caller cannot see is reported exactly like one that does
     * not exist, so another school's roster cannot be probed by id.
     */
    private function findOrRefuse(int $id, User $user): Student|JsonResponse
    {
        if ($id <= 0) {
            return response()->json(['message' => 'We could not find that student.'], 404);
        }

        try {
            return $this->students->find($id, $user);
        } catch (ValidationException $exception) {
            if ($exception->validator->errors()->has('id')) {
                return response()->json(['message' => 'We could not find that student.'], 404);
            }

            throw $exception;
        }
    }

    private function findExamOrRefuse(int $id, User $user): Exam|JsonResponse
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