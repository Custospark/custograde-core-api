<?php

namespace App\Repositories;

use App\Models\AcademicYear;
use App\Models\User;
use App\Repositories\Contracts\AcademicYearRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class AcademicYearRepository implements AcademicYearRepositoryInterface
{
    public function visibleTo(User $user): Collection
    {
        return AcademicYear::query()
            ->visibleTo($user)
            ->orderByDesc('starts_on')
            ->get();
    }

    public function findVisible(int $id, User $user): ?AcademicYear
    {
        return AcademicYear::query()
            ->visibleTo($user)
            ->whereKey($id)
            ->first();
    }

    public function currentFor(User $user): ?AcademicYear
    {
        return AcademicYear::query()
            ->visibleTo($user)
            ->where('is_current', true)
            ->orderByDesc('starts_on')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): AcademicYear
    {
        return AcademicYear::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(AcademicYear $year, array $attributes): AcademicYear
    {
        $year->fill($attributes)->save();

        return $year->refresh();
    }

    public function delete(AcademicYear $year): void
    {
        $year->delete();
    }

    public function paginateVisible(User $user, int $perPage = 25): LengthAwarePaginator
    {
        return AcademicYear::query()
            ->visibleTo($user)
            ->orderByDesc('starts_on')
            ->paginate($perPage);
    }

    public function nameExistsFor(User $user, string $name, ?int $exceptId = null): bool
    {
        $query = AcademicYear::query()->where('name', $name);

        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }

        if ($user->institution_id !== null) {
            return $query->where('institution_id', $user->institution_id)->exists();
        }

        return $query->where('owner_user_id', $user->id)->exists();
    }
}
