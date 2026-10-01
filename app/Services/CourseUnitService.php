<?php

namespace App\Services;

use App\Models\CourseUnit;
use App\Models\User;
use App\Repositories\Contracts\CourseUnitRepositoryInterface;
use App\Repositories\Contracts\OrgUnitRepositoryInterface;
use App\Services\Contracts\CourseUnitServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Course units or subjects (ACD-04).
 *
 * The org unit is checked for visibility on every write. Without that check a
 * request carrying another institution's department id would attach a course to
 * a tree it cannot see, which is a cross-tenant read dressed as a write.
 */
class CourseUnitService implements CourseUnitServiceInterface
{
    public function __construct(
        private CourseUnitRepositoryInterface $courseUnits,
        private OrgUnitRepositoryInterface $orgUnits,
    ) {}

    public function list(User $user, ?int $orgUnitId = null): Collection
    {
        if ($orgUnitId === null) {
            return $this->courseUnits->visibleTo($user);
        }

        $courseUnit = $this->orgUnits->findVisible($orgUnitId, $user);

        if ($courseUnit === null) {
            throw ValidationException::withMessages([
                'org_unit_id' => 'We could not find that unit in your institution, so we cannot list the courses under it.',
            ]);
        }

        return $courseUnit->courseUnits()->orderBy('code')->get();
    }

    public function find(int $id, User $user): CourseUnit
    {
        $courseUnit = $this->courseUnits->findVisible($id, $user);

        if ($courseUnit === null) {
            throw ValidationException::withMessages([
                'id' => 'We could not find that course in your institution. It may have been removed, or it may belong to another school.',
            ]);
        }

        return $courseUnit;
    }

    public function create(User $user, array $data): CourseUnit
    {
        $orgUnitId = $data['org_unit_id'] ?? null;

        $this->assertCodeIsFree($user, $data['code']);
        $this->assertOrgUnitIsVisible($user, $orgUnitId);

        $attributes = [
            'org_unit_id' => $orgUnitId,
            'code' => trim($data['code']),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            // credit_units is NOT NULL with a zero default. Omitting the key
            // entirely lets the column default apply; passing null would fail
            // the insert, so an explicit null becomes 0.
            'credit_units' => $data['credit_units'] ?? 0,
            'level' => $data['level'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ];

        $model = new CourseUnit;
        $model->assignTenant($user, $attributes);

        return $this->courseUnits->create($attributes);
    }

    public function update(int $id, User $user, array $data): CourseUnit
    {
        $courseUnit = $this->find($id, $user);

        if (array_key_exists('code', $data)) {
            $this->assertCodeIsFree($user, $data['code'], $courseUnit->id);
        }

        if (array_key_exists('org_unit_id', $data)) {
            $this->assertOrgUnitIsVisible($user, $data['org_unit_id']);
        }

        $attributes = array_filter(
            [
                'code' => array_key_exists('code', $data) ? trim((string) $data['code']) : null,
                'title' => $data['title'] ?? null,
                'description' => array_key_exists('description', $data) ? $data['description'] : null,
                // 0 and false are legitimate values here, so presence is
                // decided by array_key_exists rather than by truthiness.
                'credit_units' => array_key_exists('credit_units', $data) ? (float) $data['credit_units'] : null,
                'level' => array_key_exists('level', $data) ? $data['level'] : null,
                'is_active' => array_key_exists('is_active', $data) ? $data['is_active'] : null,
                'org_unit_id' => array_key_exists('org_unit_id', $data) ? $data['org_unit_id'] : null,
                'updated_by' => $user->id,
            ],
            fn (mixed $value, string $key): bool => $key === 'updated_by' || $value !== null,
            ARRAY_FILTER_USE_BOTH
        );

        return $this->courseUnits->update($courseUnit, $attributes);
    }

    public function delete(int $id, User $user): void
    {
        $courseUnit = $this->find($id, $user);

        $resourceCount = $courseUnit->resources()->count();

        if ($resourceCount > 0) {
            throw ValidationException::withMessages([
                'id' => sprintf(
                    '"%s" still has %d document(s) attached, such as past papers or marking guides. Please remove them first, or mark the course as not active if you want to keep it for past results.',
                    $courseUnit->title,
                    $resourceCount
                ),
            ]);
        }

        $teacherCount = $courseUnit->teachers()->count();

        if ($teacherCount > 0) {
            throw ValidationException::withMessages([
                'id' => sprintf(
                    '"%s" still has %d teacher(s) assigned to it. Please remove the teachers first, so nobody is left pointing at a course that no longer exists.',
                    $courseUnit->title,
                    $teacherCount
                ),
            ]);
        }

        $this->courseUnits->delete($courseUnit);
    }

    public function attachTeacher(int $id, User $user, int $teacherId, bool $isResponsible = false): void
    {
        $courseUnit = $this->find($id, $user);

        $this->assertTeacherIsVisible($user, $teacherId);

        $this->courseUnits->attachTeacher($courseUnit, $teacherId, $isResponsible);
    }

    public function detachTeacher(int $id, User $user, int $teacherId): void
    {
        $courseUnit = $this->find($id, $user);

        $attached = $courseUnit->teachers()->whereKey($teacherId)->exists();

        if (! $attached) {
            throw ValidationException::withMessages([
                'teacher_id' => 'That teacher is not assigned to this course, so there is nothing to remove.',
            ]);
        }

        $this->courseUnits->detachTeacher($courseUnit, $teacherId);
    }

    private function assertCodeIsFree(User $user, string $code, ?int $exceptId = null): void
    {
        $code = trim($code);

        if ($code === '') {
            throw ValidationException::withMessages([
                'code' => 'Please give the course a short code, for example PHY101. It is how results are traced back to this course.',
            ]);
        }

        if (! $this->courseUnits->codeExistsFor($user, $code, $exceptId)) {
            return;
        }

        throw ValidationException::withMessages([
            'code' => sprintf(
                'The course code "%s" is already in use in your institution. Please choose another code, so results cannot be confused with each other.',
                $code
            ),
        ]);
    }

    private function assertOrgUnitIsVisible(User $user, ?int $orgUnitId): void
    {
        if ($orgUnitId === null) {
            return;
        }

        $orgUnit = $this->orgUnits->findVisible($orgUnitId, $user);

        if ($orgUnit === null) {
            throw ValidationException::withMessages([
                'org_unit_id' => 'We could not find that department or unit in your institution. Please choose a unit from your own structure, because a course cannot be attached to another school.',
            ]);
        }
    }

    /**
     * A teacher must belong to the same institution, or be the acting user
     * themselves when the account is personal.
     */
    private function assertTeacherIsVisible(User $user, int $teacherId): void
    {
        if ($teacherId === $user->id) {
            return;
        }

        $teacher = User::query()->whereKey($teacherId)->first();

        $visible = $teacher !== null && $user->institution_id !== null
            && $teacher->institution_id === $user->institution_id;

        if (! $visible) {
            throw ValidationException::withMessages([
                'teacher_id' => 'We could not find that teacher in your institution, so they cannot be assigned to this course.',
            ]);
        }
    }

    }