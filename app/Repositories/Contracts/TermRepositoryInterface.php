<?php

namespace App\Repositories\Contracts;

use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Data access for terms (ACD-03).
 *
 * Every read is tenant-scoped at this layer rather than in the controller, so a
 * forgotten `visibleTo` cannot become a cross-tenant leak.
 */
interface TermRepositoryInterface
{
    /**
     * @return Collection<int, Term>
     */
    public function visibleTo(User $user): Collection;

    public function findVisible(int $id, User $user): ?Term;

    /**
     * The terms of one year in teaching order.
     *
     * @return Collection<int, Term>
     */
    public function forYear(int $academicYearId, User $user): Collection;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Term;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Term $term, array $attributes): Term;

    public function delete(Term $term): void;

    /**
     * The term flagged active for this tenant, if any.
     *
     * The earliest match wins so a calendar that was left with two open terms
     * resolves deterministically rather than at random.
     */
    public function activeFor(User $user): ?Term;

    /**
     * True when this year already uses this sequence number.
     *
     * Checked in the service so the error can be a readable message rather than
     * a raw unique-constraint violation.
     */
    public function sequenceExistsFor(User $user, int $academicYearId, int $sequence, ?int $exceptId = null): bool;
}
