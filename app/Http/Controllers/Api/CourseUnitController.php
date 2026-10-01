<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttachTeacherRequest;
use App\Http\Requests\DetachTeacherRequest;
use App\Http\Requests\StoreCourseUnitRequest;
use App\Http\Requests\UpdateCourseUnitRequest;
use App\Http\Resources\CourseUnitCollection;
use App\Http\Resources\CourseUnitResource;
use App\Models\CourseUnit;
use App\Models\User;
use App\Services\Contracts\CourseUnitServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CourseUnitController extends Controller
{
    public function __construct(
        protected CourseUnitServiceInterface $courses,
    ) {}

    /**
     * Every course visible to this user, optionally narrowed to one unit of the
     * academic structure. The tenant scope is the service's, not the
     * controller's, so a course from another school is never returned.
     *
     * @return array<string, mixed>
     */
    public function index(Request $request): CourseUnitCollection
    {
        $orgUnitId = $request->integer('org_unit_id') ?: null;

        return new CourseUnitCollection($this->courses->list($request->user(), $orgUnitId));
    }

    /**
     * @return array<string, mixed>
     */
    public function store(StoreCourseUnitRequest $request): JsonResponse
    {
        $course = $this->courses->create($request->user(), $request->validated());

        return response()->json(new CourseUnitResource($course->load('orgUnit')), 201);
    }

    /**
     * @return array<string, mixed>
     */
    public function show(Request $request, string $id): CourseUnitResource|JsonResponse
    {
        $course = $this->findOrRefuse((int) $id, $request->user());

        return $course instanceof CourseUnit
            ? new CourseUnitResource($course->load(['orgUnit', 'teachers']))
            : $course;
    }

    /**
     * @return array<string, mixed>
     */
    public function update(UpdateCourseUnitRequest $request, string $id): CourseUnitResource|JsonResponse
    {
        $course = $this->findOrRefuse((int) $id, $request->user());

        if (! $course instanceof CourseUnit) {
            return $course;
        }

        $updated = $this->courses->update($course->id, $request->user(), $request->validated());

        return new CourseUnitResource($updated->load('orgUnit'));
    }

    /**
     * @return array<string, mixed>
     */
    public function attachTeacher(AttachTeacherRequest $request, string $id): JsonResponse
    {
        $course = $this->findOrRefuse((int) $id, $request->user());

        if (! $course instanceof CourseUnit) {
            return $course;
        }

        $data = $request->validated();

        $this->courses->attachTeacher(
            $course->id,
            $request->user(),
            (int) $data['teacher_id'],
            (bool) ($data['is_responsible'] ?? false),
        );

        return response()->json([
            'message' => 'The teacher has been added to this course.',
            'course' => new CourseUnitResource($course->load('teachers')),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    public function detachTeacher(DetachTeacherRequest $request, string $id): JsonResponse
    {
        $course = $this->findOrRefuse((int) $id, $request->user());

        if (! $course instanceof CourseUnit) {
            return $course;
        }

        $teacherId = (int) $request->validated()['teacher_id'];

        $this->courses->detachTeacher($course->id, $request->user(), $teacherId);

        return response()->json([
            'message' => 'The teacher has been removed from this course.',
            'course' => new CourseUnitResource($course->load('teachers')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $course = $this->findOrRefuse((int) $id, $request->user());

        if (! $course instanceof CourseUnit) {
            return $course;
        }

        // The service refuses while the course still carries documents or has
        // teachers attached, so marks are never left pointing at nothing.
        $this->courses->delete($course->id, $request->user());

        return response()->json([
            'message' => sprintf('The course "%s" has been deleted.', $course->title),
        ]);
    }

    /**
     * A course the caller cannot see is reported exactly like one that does not
     * exist, so a teacher cannot probe another school's course list by id.
     */
    private function findOrRefuse(int $id, User $user): CourseUnit|JsonResponse
    {
        if ($id <= 0) {
            return response()->json(['message' => 'We could not find that course.'], 404);
        }

        try {
            return $this->courses->find($id, $user);
        } catch (ValidationException $exception) {
            if ($exception->validator->errors()->has('id')) {
                return response()->json(['message' => 'We could not find that course.'], 404);
            }

            throw $exception;
        }
    }
}