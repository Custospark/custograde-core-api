<?php

namespace App\Repositories;

use App\Models\CourseUnit;
use App\Models\User;
use App\Repositories\Contracts\CourseUnitRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class CourseUnitRepository implements CourseUnitRepositoryInterface
{
    public function visibleTo(User $user): Collection
    {
        return CourseUnit::query()
            ->visibleTo($user)
            ->orderBy('code')
            ->get();
    }

    public function findVisible(int $id, User $user): ?CourseUnit
    {
        return CourseUnit::query()
            ->visibleTo($user)
            ->whereKey($id)
            ->first();
    }

    public function withRelations(int $id, User $user): ?CourseUnit
    {
        return CourseUnit::query()
            ->visibleTo($user)
            ->with(['orgUnit', 'teachers'])
            ->whereKey($id)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): CourseUnit
    {
        return CourseUnit::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(CourseUnit $courseUnit, array $attributes): CourseUnit
    {
        $courseUnit->fill($attributes)->save();

        return $courseUnit->refresh();
    }

    public function delete(CourseUnit $courseUnit): void
    {
        $courseUnit->delete();
    }

    public function codeExistsFor(User $user, string $code, ?int $exceptId = null): bool
    {
        $query = CourseUnit::query()
            ->visibleTo($user)
            ->where('code', $code);

        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }

        return $query->exists();
    }

    public function attachTeacher(CourseUnit $courseUnit, int $userId, bool $isResponsible = false): void
    {
        $courseUnit->teachers()->syncWithoutDetaching([
            $userId => ['is_responsible' => $isResponsible],
        ]);
    }

    public function detachTeacher(CourseUnit $courseUnit, int $userId): void
    {
        $courseUnit->teachers()->detach($userId);
    }
}
