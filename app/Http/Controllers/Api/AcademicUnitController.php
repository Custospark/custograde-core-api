<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MoveAcademicUnitRequest;
use App\Http\Requests\StoreAcademicUnitRequest;
use App\Http\Requests\UpdateAcademicUnitRequest;
use App\Http\Resources\OrgUnitCollection;
use App\Http\Resources\OrgUnitResource;
use App\Models\OrgUnit;
use App\Models\User;
use App\Services\Contracts\AcademicUnitServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AcademicUnitController extends Controller
{
    public function __construct(
        protected AcademicUnitServiceInterface $academicUnits,
    ) {}

    /**
     * The whole visible tree, or one branch when a parent is given. The service
     * owns the tenant scope and the branch rules, so no filtering happens here.
     *
     * @return array<string, mixed>
     */
    public function index(Request $request): OrgUnitCollection
    {
        $parentId = $request->integer('parent_id') ?: null;

        return new OrgUnitCollection($this->academicUnits->list($request->user(), $parentId));
    }

    /**
     * @return array<string, mixed>
     */
    public function store(StoreAcademicUnitRequest $request): JsonResponse
    {
        $unit = $this->academicUnits->create($request->user(), $request->validated());

        return response()->json(new OrgUnitResource($unit), 201);
    }

    /**
     * @return array<string, mixed>
     */
    public function show(Request $request, string $id): OrgUnitResource|JsonResponse
    {
        $unit = $this->findOrRefuse((int) $id, $request->user());

        return $unit instanceof OrgUnit
            ? new OrgUnitResource($unit->load(['parent', 'children']))
            : $unit;
    }

    /**
     * Name, code and active flag only. The type and the parent are not editable
     * here, because moving a branch changes what every unit beneath it means.
     *
     * @return array<string, mixed>
     */
    public function update(UpdateAcademicUnitRequest $request, string $id): OrgUnitResource|JsonResponse
    {
        $unit = $this->findOrRefuse((int) $id, $request->user());

        if (! $unit instanceof OrgUnit) {
            return $unit;
        }

        $updated = $this->academicUnits->update($unit->id, $request->user(), $request->validated());

        return new OrgUnitResource($updated);
    }

    /**
     * @return array<string, mixed>
     */
    public function move(MoveAcademicUnitRequest $request, string $id): OrgUnitResource|JsonResponse
    {
        $unit = $this->findOrRefuse((int) $id, $request->user());

        if (! $unit instanceof OrgUnit) {
            return $unit;
        }

        $parentId = $request->validated()['parent_id'] ?? null;

        $moved = $this->academicUnits->move($unit->id, $request->user(), $parentId === null ? null : (int) $parentId);

        return new OrgUnitResource($moved->load('parent'));
    }

    /**
     * @return array<string, mixed>
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $unit = $this->findOrRefuse((int) $id, $request->user());

        if (! $unit instanceof OrgUnit) {
            return $unit;
        }

        // The service refuses while anything still hangs off the unit, so a
        // department is never detached from its courses by a deletion.
        $this->academicUnits->delete($unit->id, $request->user());

        return response()->json(['message' => sprintf('"%s" has been deleted.', $unit->name)]);
    }

    /**
     * A unit the caller cannot see is reported exactly like one that does not
     * exist, which is what stops another institution's structure being probed.
     */
    private function findOrRefuse(int $id, User $user): OrgUnit|JsonResponse
    {
        if ($id <= 0) {
            return response()->json(['message' => 'We could not find that part of your academic structure.'], 404);
        }

        try {
            return $this->academicUnits->find($id, $user);
        } catch (ValidationException $exception) {
            if ($exception->validator->errors()->has('id')) {
                return response()->json(['message' => 'We could not find that part of your academic structure.'], 404);
            }

            throw $exception;
        }
    }
}