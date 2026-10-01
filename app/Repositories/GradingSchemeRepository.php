<?php

namespace App\Repositories;

use App\Models\GradeBand;
use App\Models\GradingScheme;
use App\Models\User;
use App\Repositories\Contracts\GradingSchemeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class GradingSchemeRepository implements GradingSchemeRepositoryInterface
{
    public function visibleTo(User $user): Collection
    {
        return GradingScheme::query()
            ->visibleTo($user)
            ->orderBy('name')
            ->get();
    }

    public function findVisible(int $id, User $user): ?GradingScheme
    {
        return GradingScheme::query()
            ->visibleTo($user)
            ->whereKey($id)
            ->first();
    }

    public function withBands(int $id, User $user): ?GradingScheme
    {
        return GradingScheme::query()
            ->visibleTo($user)
            ->with('bands')
            ->whereKey($id)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): GradingScheme
    {
        return GradingScheme::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(GradingScheme $scheme, array $attributes): GradingScheme
    {
        $scheme->fill($attributes)->save();

        return $scheme->refresh();
    }

    public function delete(GradingScheme $scheme): void
    {
        $scheme->delete();
    }

    public function defaultFor(User $user): ?GradingScheme
    {
        return GradingScheme::query()
            ->visibleTo($user)
            ->where('is_default', true)
            ->orderByDesc('effective_from')
            ->first();
    }

    public function nameExistsFor(User $user, string $name, ?int $exceptId = null): bool
    {
        $query = GradingScheme::query()
            ->visibleTo($user)
            ->where('name', $name);

        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }

        return $query->exists();
    }

    /**
     * @param  array<int, array<string, mixed>>  $bands
     * @return Collection<int, GradeBand>
     */
    public function replaceBands(GradingScheme $scheme, array $bands): Collection
    {
        $scheme->bands()->delete();

        $saved = new Collection;

        foreach (array_values($bands) as $index => $band) {
            $saved->push($scheme->bands()->create(
                array_merge($band, ['sort_order' => $index])
            ));
        }

        $scheme->unsetRelation('bands');

        return $saved;
    }
}
