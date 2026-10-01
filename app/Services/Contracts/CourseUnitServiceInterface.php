<?php

namespace App\Services\Contracts;

use App\Models\CourseUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Course units or subjects (ACD-04): the anchor a teacher registers.
 *
 * The org unit a course hangs from is checked for visibility on every write, so
 * a course can never be attached to another institution's department.
 */
interface CourseUnitServiceInterface
{
    /**
     * @return Collection<int, CourseUnit>
     */
    public function list(User $user, ?int $orgUnitId = null): Collection;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function find(int $id, User $user): CourseUnit;

    /**
     * @param array{
     *     code: string,
     *     title: string,
     *     org_unit_id?: int|null,
     *     description?: string|null,
     *     credit_units?: float|string|null,
     *     level?: int|null,
     *     is_active?: bool
     * } $data
     */
    public function create(User $user, array $data): CourseUnit;

    /**
     * @param array{
     *     code?: string,
     *     title?: string,
     *     org_unit_id?: int|null,
     *     description?: string|null,
     *     credit_units?: float|string|null,
     *     level?: int|null,
     *     is_active?: bool
     * } $data
     */
    public function update(int $id, User $user, array $data): CourseUnit;

    /**
     * Refuses while the course still carries documents or is referenced by
     * marking activity, so marks are never left pointing at nothing.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function delete(int $id, User $user): void;

    /**
     * Attach a teacher, promoting them to the responsible lecturer when asked.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function attachTeacher(int $id, User $user, int $teacherId, bool $isResponsible = false): void;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function detachTeacher(int $id, User $user, int $teacherId): void;
}