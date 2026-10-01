<?php

namespace App\Services\Contracts;

use App\Models\GradeBand;
use App\Models\GradingScheme;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Grading schemes and their bands (ACD-05).
 *
 * Bands are validated as a set before they are written, because a gap or an
 * overlap would leave a percentage ungradeable, or gradeable twice.
 */
interface GradingSchemeServiceInterface
{
    /**
     * @return Collection<int, GradingScheme>
     */
    public function list(User $user): Collection;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function find(int $id, User $user): GradingScheme;

    /**
     * @param array{
     *     name: string,
     *     pass_mark: float|int|string,
     *     description?: string|null,
     *     effective_from: string,
     *     effective_to?: string|null,
     *     is_default?: bool,
     *     bands?: array<int, array{grade: string, min_percent: float|int|string, max_percent: float|int|string, grade_point?: float|int|string|null, remark?: string|null}>
     * } $data
     */
    public function create(User $user, array $data): GradingScheme;

    /**
     * @param array{
     *     name?: string,
     *     pass_mark?: float|int|string,
     *     description?: string|null,
     *     effective_from?: string,
     *     effective_to?: string|null,
     *     is_default?: bool
     * } $data
     */
    public function update(int $id, User $user, array $data): GradingScheme;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function delete(int $id, User $user): void;

    /**
     * Swap the bands for a new set. Refuses the whole set if any percentage is
     * covered twice or not at all, and lists every problem found.
     *
     * @param  array<int, array{grade: string, min_percent: float|int|string, max_percent: float|int|string, grade_point?: float|int|string|null, remark?: string|null}>  $bands
     * @return Collection<int, GradeBand>
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function replaceBands(int $id, User $user, array $bands): Collection;
}