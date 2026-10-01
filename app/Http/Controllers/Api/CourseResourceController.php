<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourseResourceRequest;
use App\Http\Requests\UpdateCourseResourceRequest;
use App\Http\Resources\CourseResourceCollection;
use App\Http\Resources\CourseResourceResource;
use App\Models\CourseResource;
use App\Models\User;
use App\Services\Contracts\CourseResourceServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CourseResourceController extends Controller
{
    public function __construct(
        protected CourseResourceServiceInterface $resources,
    ) {}

    /**
     * Documents on one course. Current versions only by default, because a
     * superseded guide is only interesting to somebody auditing past marks. Pass
     * include_superseded=1 to see the full history.
     *
     * @return array<string, mixed>
     */
    public function index(Request $request, string $courseUnitId): CourseResourceCollection
    {
        $currentOnly = ! $request->boolean('include_superseded');

        return new CourseResourceCollection(
            $this->resources->listForCourse((int) $courseUnitId, $request->user(), $currentOnly)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function store(StoreCourseResourceRequest $request, string $courseUnitId): JsonResponse
    {
        $data = $request->validated();

        $resource = $this->resources->upload(
            $request->user(),
            (int) $courseUnitId,
            $request->file('file'),
            array_filter([
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'kind' => $data['kind'] ?? null,
            ], fn (mixed $value): bool => $value !== null),
        );

        return response()->json(new CourseResourceResource($resource->load('uploader')), 201);
    }

    /**
     * @return array<string, mixed>
     */
    public function show(Request $request, string $id): CourseResourceResource|JsonResponse
    {
        $resource = $this->findOrRefuse((int) $id, $request->user());

        return $resource instanceof CourseResource
            ? new CourseResourceResource($resource->load(['uploader', 'courseUnit']))
            : $resource;
    }

    /**
     * Details only. The stored file is never swapped by an edit, because a new
     * document is a new version and overwriting would break earlier marks.
     *
     * @return array<string, mixed>
     */
    public function update(UpdateCourseResourceRequest $request, string $id): CourseResourceResource|JsonResponse
    {
        $resource = $this->findOrRefuse((int) $id, $request->user());

        if (! $resource instanceof CourseResource) {
            return $resource;
        }

        $updated = $this->resources->update($resource->id, $request->user(), $request->validated());

        return new CourseResourceResource($updated->load('uploader'));
    }

    /**
     * Rolls one version back to being the current document of its kind without
     * deleting the newer upload, so the change stays visible rather than silent.
     *
     * @return array<string, mixed>
     */
    public function setCurrentVersion(Request $request, string $id): CourseResourceResource|JsonResponse
    {
        $resource = $this->findOrRefuse((int) $id, $request->user());

        if (! $resource instanceof CourseResource) {
            return $resource;
        }

        $current = $this->resources->setCurrentVersion($resource->id, $request->user());

        return new CourseResourceResource($current->load('uploader'));
    }

    /**
     * @return array<string, mixed>
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $resource = $this->findOrRefuse((int) $id, $request->user());

        if (! $resource instanceof CourseResource) {
            return $resource;
        }

        // The service removes the row and the stored file together, so nothing
        // is left behind holding storage.
        $this->resources->delete($resource->id, $request->user());

        return response()->json([
            'message' => sprintf('The document "%s" has been deleted.', $resource->title),
        ]);
    }

    /**
     * A document the caller cannot see is reported exactly like one that does
     * not exist, so another school's documents cannot be probed by id.
     */
    private function findOrRefuse(int $id, User $user): CourseResource|JsonResponse
    {
        if ($id <= 0) {
            return response()->json(['message' => 'We could not find that document.'], 404);
        }

        try {
            return $this->resources->find($id, $user);
        } catch (ValidationException $exception) {
            if ($exception->validator->errors()->has('id')) {
                return response()->json(['message' => 'We could not find that document.'], 404);
            }

            throw $exception;
        }
    }
}