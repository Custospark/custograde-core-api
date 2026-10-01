<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAcademicYearRequest;
use App\Http\Requests\UpdateAcademicYearRequest;
use App\Http\Resources\AcademicYearCollection;
use App\Http\Resources\AcademicYearResource;
use App\Models\AcademicYear;
use App\Models\User;
use App\Repositories\Contracts\AcademicYearRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcademicYearController extends Controller
{
    public function __construct(
        protected AcademicYearRepositoryInterface $academicYears,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min($request->integer('per_page', 25), 100));
        $years = $this->academicYears->paginateVisible($request->user(), $perPage);

        // Pagination is kept because a school can hold many years and a bare
        // list would silently truncate. It is merged as a sibling key rather
        // than via additional(), because additional() nests the payload under
        // `data` and every other list in this API is a plain unwrapped array.
        return response()->json([
            'years' => AcademicYearResource::collection($years->getCollection())->resolve(),
            'meta' => [
                'current_page' => $years->currentPage(),
                'last_page' => $years->lastPage(),
                'per_page' => $years->perPage(),
                'total' => $years->total(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function store(StoreAcademicYearRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $this->assertNameIsFree($user, $data['name']);
        $this->assertDatesRunForward($data['starts_on'], $data['ends_on']);

        $attributes = [
            'name' => trim($data['name']),
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'is_current' => (bool) ($data['is_current'] ?? false),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ];

        $model = new AcademicYear;
        $model->assignTenant($user, $attributes);

        $created = DB::transaction(function () use ($user, $attributes): AcademicYear {
            if ($attributes['is_current']) {
                $this->clearCurrentOnOthers($user, null);
            }

            return $this->academicYears->create($attributes);
        });

        return response()->json(new AcademicYearResource($created), 201);
    }

    /**
     * @return array<string, mixed>
     */
    public function show(Request $request, string $id): AcademicYearResource|JsonResponse
    {
        $year = $this->findOrRefuse((int) $id, $request->user());

        return $year instanceof AcademicYear
            ? new AcademicYearResource($year->load('currentTerm'))
            : $year;
    }

    /**
     * @return array<string, mixed>
     */
    public function update(UpdateAcademicYearRequest $request, string $id): AcademicYearResource|JsonResponse
    {
        $user = $request->user();
        $year = $this->findOrRefuse((int) $id, $user);

        if (! $year instanceof AcademicYear) {
            return $year;
        }

        $data = $request->validated();

        if (array_key_exists('name', $data)) {
            $this->assertNameIsFree($user, $data['name'], $year->id);
        }

        $this->assertDatesRunForward(
            $data['starts_on'] ?? $year->starts_on?->toDateString(),
            $data['ends_on'] ?? $year->ends_on?->toDateString(),
        );

        $attributes = array_filter([
            'name' => array_key_exists('name', $data) ? trim($data['name']) : null,
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
            'is_current' => array_key_exists('is_current', $data) ? (bool) $data['is_current'] : null,
            'updated_by' => $user->id,
        ], fn (mixed $value, string $key): bool => $key === 'updated_by' || $value !== null, ARRAY_FILTER_USE_BOTH);

        $updated = DB::transaction(function () use ($user, $year, $attributes): AcademicYear {
            if (($attributes['is_current'] ?? false) === true) {
                $this->clearCurrentOnOthers($user, $year->id);
            }

            return $this->academicYears->update($year, $attributes);
        });

        return new AcademicYearResource($updated);
    }

    /**
     * @return array<string, mixed>
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $year = $this->findOrRefuse((int) $id, $request->user());

        if (! $year instanceof AcademicYear) {
            return $year;
        }

        // A year with terms inside it is refused rather than cascaded, because
        // silently deleting a term would take its examinations with it.
        if ($year->terms()->exists()) {
            throw ValidationException::withMessages([
                'id' => sprintf(
                    '"%s" still has terms set up inside it. Please delete those terms first, so no term is left pointing at a year that no longer exists.',
                    $year->name
                ),
            ]);
        }

        $this->academicYears->delete($year);

        return response()->json(['message' => sprintf('The academic year "%s" has been deleted.', $year->name)]);
    }

    /**
     * The running year for this user, or null when none has been set. A client
     * uses this on load to preselect the year a teacher is working in.
     *
     * @return array<string, mixed>
     */
    public function current(Request $request): JsonResponse
    {
        $year = $this->academicYears->currentFor($request->user());

        return response()->json([
            'academic_year' => $year === null
                ? null
                : new AcademicYearResource($year->load('currentTerm')),
        ]);
    }

    /**
     * A year the caller cannot see is reported exactly like one that does not
     * exist. Confirming an id exists in another institution would let a teacher
     * map another school's calendar, so existence never leaks across tenants.
     */
    private function findOrRefuse(int $id, User $user): AcademicYear|JsonResponse
    {
        $year = $id > 0 ? $this->academicYears->findVisible($id, $user) : null;

        return $year ?? response()->json(['message' => 'We could not find that academic year.'], 404);
    }

    private function assertNameIsFree(User $user, string $name, ?int $exceptId = null): void
    {
        if (! $this->academicYears->nameExistsFor($user, $name, $exceptId)) {
            return;
        }

        throw ValidationException::withMessages([
            'name' => sprintf(
                'You already have an academic year called "%s". Please use a different name so your calendar stays readable.',
                $name
            ),
        ]);
    }

    private function assertDatesRunForward(?string $startsOn, ?string $endsOn): void
    {
        if ($startsOn === null || $endsOn === null) {
            return;
        }

        if (strtotime($endsOn) < strtotime($startsOn)) {
            throw ValidationException::withMessages([
                'ends_on' => sprintf('"%s" ends before it starts. Please check the dates you entered.', $endsOn),
            ]);
        }
    }

    /**
     * Only one year may be flagged current per tenant. Clearing the flag on the
     * others and writing this one happen in the same transaction, so a failure
     * never leaves two years claiming to be the running one.
     */
    private function clearCurrentOnOthers(User $user, ?int $keepId): void
    {
        $this->academicYears->visibleTo($user)
            ->when($keepId !== null, fn ($query) => $query->whereKeyNot($keepId))
            ->where('is_current', true)
            ->get()
            ->each(fn (AcademicYear $other) => $this->academicYears->update($other, [
                'is_current' => false,
                'updated_by' => $user->id,
            ]));
    }
}