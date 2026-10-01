<?php

namespace App\Repositories;

use App\Models\OrgUnit;
use App\Models\User;
use App\Repositories\Contracts\OrgUnitRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class OrgUnitRepository implements OrgUnitRepositoryInterface
{
    public function visibleTo(User $user): Collection
    {
        return OrgUnit::query()
            ->visibleTo($user)
            ->orderBy('depth')
            ->orderBy('name')
            ->get();
    }

    public function findVisible(int $id, User $user): ?OrgUnit
    {
        return OrgUnit::query()
            ->visibleTo($user)
            ->whereKey($id)
            ->first();
    }

    public function allVisible(User $user): Collection
    {
        return $this->visibleTo($user);
    }

    public function childrenOf(int $parentId, User $user): Collection
    {
        return OrgUnit::query()
            ->visibleTo($user)
            ->where('parent_id', $parentId)
            ->orderBy('depth')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): OrgUnit
    {
        return OrgUnit::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(OrgUnit $unit, array $attributes): OrgUnit
    {
        $unit->fill($attributes)->save();

        return $unit->refresh();
    }

    public function delete(OrgUnit $unit): void
    {
        $unit->delete();
    }

    public function codeExistsFor(User $user, string $code, ?int $exceptId = null): bool
    {
        $query = OrgUnit::query()
            ->visibleTo($user)
            ->where('code', $code);

        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }

        return $query->exists();
    }
}
