<?php

namespace App\Repositories\Contracts;

use App\Models\CourseResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Data access for course resources (ACD-02, EXM-03, EXM-07).
 *
 * Course resources carry no institution_id of their own that can be trusted in
 * isolation, so tenancy is resolved through the owning course unit. Every read
 * here scopes through that relation rather than through a resource column.
 */
interface CourseResourceRepositoryInterface
{
    /**
     * @return Collection<int, CourseResource>
     */
    public function visibleTo(User $user): Collection;

    public function findVisible(int $id, User $user): ?CourseResource;

    /**
     * Documents attached to one course.
     *
     * Defaults to current versions only, which is what a teacher expects to see
     * on a course page. Superseded versions stay reachable by passing false,
     * because EXM-07 requires earlier results to remain re-derivable.
     *
     * @return Collection<int, CourseResource>
     */
    public function forCourseUnit(int $courseUnitId, User $user, bool $currentOnly = true): Collection;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): CourseResource;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(CourseResource $resource, array $attributes): CourseResource;

    public function delete(CourseResource $resource): void;

    /**
     * The newest version of one document kind on a course.
     *
     * Used when building an examination, which must pin a guide version rather
     * than assume the newest one at marking time.
     */
    public function latestVersionFor(int $courseUnitId, string $kind, User $user): ?CourseResource;
}
