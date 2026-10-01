<?php

namespace App\Repositories\Contracts;

use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Data access for academic years (ACD-03).
 *
 * Every read is tenant-scoped at this layer rather than in the controller, so a
 * forgotten `visibleTo` cannot become a cross-tenant leak.
 */
interface AcademicYearRepositoryInterface
{
    /**
     * @return Collection<int, AcademicYear>
     */
    public function visibleTo(User $user): Collection;

    public function findVisible(int $id, User $user): ?AcademicYear;

    /**
     * The year flagged current for this tenant, if any.
     */
    public function currentFor(User $user): ?AcademicYear;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): AcademicYear;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(AcademicYear $year, array $attributes): AcademicYear;

    public function delete(AcademicYear $year): void;

    /**
     * Page through a tenant's years.
     */
    public function paginateVisible(User $user, int $perPage = 25): LengthAwarePaginator;

    /**
     * True when this tenant already has a year with this name.
     *
     * Checked in the service so the error can be a readable message rather than
     * a raw unique-constraint violation, which the institution branch would
     * otherwise surface to a teacher.
     */
    public function nameExistsFor(User $user, string $name, ?int $exceptId = null): bool;
}
