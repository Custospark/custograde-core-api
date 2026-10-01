<?php

namespace App\Repositories;

use App\Models\Term;
use App\Models\User;
use App\Repositories\Contracts\TermRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class TermRepository implements TermRepositoryInterface
{
    public function visibleTo(User $user): Collection
    {
        return Term::query()
            ->visibleTo($user)
            ->orderBy('academic_year_id')
            ->orderBy('sequence')
            ->get();
    }

    public function findVisible(int $id, User $user): ?Term
    {
        return Term::query()
            ->visibleTo($user)
            ->whereKey($id)
            ->first();
    }

    public function forYear(int $academicYearId, User $user): Collection
    {
        return Term::query()
            ->visibleTo($user)
            ->where('academic_year_id', $academicYearId)
            ->orderBy('sequence')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Term
    {
        return Term::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Term $term, array $attributes): Term
    {
        $term->fill($attributes)->save();

        return $term->refresh();
    }

    public function delete(Term $term): void
    {
        $term->delete();
    }

    public function activeFor(User $user): ?Term
    {
        return Term::query()
            ->visibleTo($user)
            ->where('status', Term::STATUS_ACTIVE)
            ->orderBy('starts_on')
            ->first();
    }

    public function sequenceExistsFor(User $user, int $academicYearId, int $sequence, ?int $exceptId = null): bool
    {
        $query = Term::query()
            ->visibleTo($user)
            ->where('academic_year_id', $academicYearId)
            ->where('sequence', $sequence);

        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }

        return $query->exists();
    }
}
