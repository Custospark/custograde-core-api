<?php

namespace App\Repositories\Contracts;

use App\Models\GradeBand;
use App\Models\GradingScheme;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Data access for grading schemes and their bands (ACD-05).
 *
 * Bands carry no tenancy columns of their own. They are reached through their
 * scheme, so a band read is always a scheme read first.
 */
interface GradingSchemeRepositoryInterface
{
    /**
     * @return Collection<int, GradingScheme>
     */
    public function visibleTo(User $user): Collection;

    public function findVisible(int $id, User $user): ?GradingScheme;

    /**
     * One scheme with its bands loaded in grading order.
     */
    public function withBands(int $id, User $user): ?GradingScheme;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): GradingScheme;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(GradingScheme $scheme, array $attributes): GradingScheme;

    public function delete(GradingScheme $scheme): void;

    /**
     * The scheme applied when a teacher has not chosen one, if any.
     *
     * Falls back to the newest scheme by effective date so a result is always
     * derivable even on a tenant that never marked a scheme as default.
     */
    public function defaultFor(User $user): ?GradingScheme;

    /**
     * True when this tenant already has a scheme with this name.
     *
     * Checked in the service so the error can be a readable message rather than
     * a raw unique-constraint violation.
     */
    public function nameExistsFor(User $user, string $name, ?int $exceptId = null): bool;

    /**
     * Swap a scheme's bands for a new set, in the order given.
     *
     * Replace rather than merge because ACD-05 has a scheme in force recorded
     * with a result, so an edited scheme must never leave half of the old bands
     * behind. The service validates the set for gaps and overlaps first.
     *
     * @param  array<int, array<string, mixed>>  $bands
     * @return Collection<int, GradeBand>
     */
    public function replaceBands(GradingScheme $scheme, array $bands): Collection;
}
