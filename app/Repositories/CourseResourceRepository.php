<?php

namespace App\Repositories;

use App\Models\CourseResource;
use App\Models\User;
use App\Repositories\Contracts\CourseResourceRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class CourseResourceRepository implements CourseResourceRepositoryInterface
{
    public function visibleTo(User $user): Collection
    {
        return $this->scopedTo($user)
            ->orderByDesc('version')
            ->get();
    }

    public function findVisible(int $id, User $user): ?CourseResource
    {
        return $this->scopedTo($user)
            ->whereKey($id)
            ->first();
    }

    public function forCourseUnit(int $courseUnitId, User $user, bool $currentOnly = true): Collection
    {
        $query = $this->scopedTo($user)
            ->where('course_unit_id', $courseUnitId)
            ->orderBy('kind')
            ->orderByDesc('version');

        if ($currentOnly) {
            $query->where('is_current', true);
        }

        return $query->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): CourseResource
    {
        return CourseResource::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(CourseResource $resource, array $attributes): CourseResource
    {
        $resource->fill($attributes)->save();

        return $resource->refresh();
    }

    public function delete(CourseResource $resource): void
    {
        $resource->delete();
    }

    public function latestVersionFor(int $courseUnitId, string $kind, User $user): ?CourseResource
    {
        return $this->scopedTo($user)
            ->where('course_unit_id', $courseUnitId)
            ->where('kind', $kind)
            ->orderByDesc('version')
            ->first();
    }

    /**
     * Tenancy for a resource is decided by the course unit that owns it, so the
     * scope is applied through that relation rather than a local column.
     */
    private function scopedTo(User $user): Builder
    {
        return CourseResource::query()
            ->whereHas('courseUnit', fn (Builder $query) => $query->visibleTo($user));
    }
}
