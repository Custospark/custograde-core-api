<?php

namespace App\Repositories\Contracts;

use App\Models\OrgUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Data access for org units (ACD-02).
 *
 * Every read is tenant-scoped at this layer rather than in the controller, so a
 * forgotten `visibleTo` cannot become a cross-tenant leak.
 */
interface OrgUnitRepositoryInterface
{
    /**
     * @return Collection<int, OrgUnit>
     */
    public function visibleTo(User $user): Collection;

    public function findVisible(int $id, User $user): ?OrgUnit;

    /**
     * The whole visible tree, shallowest level first.
     *
     * Ordered by depth then name so a caller can render the hierarchy by
     * indenting each row by its depth rather than walking the tree itself.
     *
     * @return Collection<int, OrgUnit>
     */
    public function allVisible(User $user): Collection;

    /**
     * Direct children of one node, still tenant-scoped.
     *
     * @return Collection<int, OrgUnit>
     */
    public function childrenOf(int $parentId, User $user): Collection;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): OrgUnit;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(OrgUnit $unit, array $attributes): OrgUnit;

    public function delete(OrgUnit $unit): void;

    /**
     * True when this tenant already uses this code anywhere in its tree.
     *
     * Checked in the service so the error can be a readable message rather than
     * a raw unique-constraint violation.
     */
    public function codeExistsFor(User $user, string $code, ?int $exceptId = null): bool;
}
