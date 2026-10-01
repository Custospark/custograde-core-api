<?php

namespace App\Repositories\Contracts;

use App\Models\CourseUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Data access for course units (ACD-04).
 *
 * Every read is tenant-scoped at this layer rather than in the controller, so a
 * forgotten `visibleTo` cannot become a cross-tenant leak.
 */
interface CourseUnitRepositoryInterface
{
    /**
     * @return Collection<int, CourseUnit>
     */
    public function visibleTo(User $user): Collection;

    public function findVisible(int $id, User $user): ?CourseUnit;

    /**
     * One course with its org unit and teachers already loaded.
     *
     * The two reads a detail screen always makes, so the service does not have
     * to remember to guard against lazy loading in a loop.
     */
    public function withRelations(int $id, User $user): ?CourseUnit;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): CourseUnit;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(CourseUnit $courseUnit, array $attributes): CourseUnit;

    public function delete(CourseUnit $courseUnit): void;

    /**
     * True when this tenant already uses this course code.
     *
     * Checked in the service so the error can be a readable message rather than
     * a raw unique-constraint violation.
     */
    public function codeExistsFor(User $user, string $code, ?int $exceptId = null): bool;

    /**
     * Attach a teacher, promoting them to the responsible lecturer when asked.
     *
     * The pivot carries no tenancy columns of its own; it is reachable only
     * through a course unit, which is why assignment happens from the course
     * side rather than through a repository of its own.
     */
    public function attachTeacher(CourseUnit $courseUnit, int $userId, bool $isResponsible = false): void;

    public function detachTeacher(CourseUnit $courseUnit, int $userId): void;
}
