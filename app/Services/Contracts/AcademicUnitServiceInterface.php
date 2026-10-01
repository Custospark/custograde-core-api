<?php

namespace App\Services\Contracts;

use App\Models\OrgUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * The academic hierarchy (ACD-02): faculty, department, programme, class.
 *
 * The tree rules live here rather than in the controller so every entry point,
 * including seeds and imports, gets the same readable refusals.
 */
interface AcademicUnitServiceInterface
{
    /**
     * The units this user may see. Pass a parent to get one branch, omit it
     * for the whole visible tree.
     *
     * @return Collection<int, OrgUnit>
     */
    public function list(User $user, ?int $parentId = null): Collection;

    /**
     * One unit inside the acting user's own institution, or a readable refusal.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function find(int $id, User $user): OrgUnit;

    /**
     * @param array{
     *     name: string,
     *     type: string,
     *     code?: string|null,
     *     parent_id?: int|null,
     *     is_active?: bool
     * } $data
     */
    public function create(User $user, array $data): OrgUnit;

    /**
     * The type and the parent are deliberately not editable through update,
     * because moving a branch changes the meaning of everything beneath it.
     * Use move() for that.
     *
     * @param array{
     *     name?: string,
     *     code?: string|null,
     *     is_active?: bool
     * } $data
     */
    public function update(int $id, User $user, array $data): OrgUnit;

    /**
     * Refuses while anything still hangs off the unit, so a deletion never
     * silently detaches a department or a course.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function delete(int $id, User $user): void;

    /**
     * Put a unit under a new parent, or make it a top level unit by passing null.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function move(int $id, User $user, ?int $newParentId): OrgUnit;
}