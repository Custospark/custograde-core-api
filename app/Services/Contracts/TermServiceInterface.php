<?php

namespace App\Services\Contracts;

use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Terms within an academic year (ACD-03).
 *
 * A tenant may run one open term at a time inside a year, so opening a term is
 * a two-write operation and belongs in a transaction.
 */
interface TermServiceInterface
{
    /**
     * Terms in teaching order, optionally narrowed to one academic year.
     *
     * @return Collection<int, Term>
     */
    public function list(User $user, ?int $academicYearId = null): Collection;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function find(int $id, User $user): Term;

    /**
     * The term is placed inside its academic year, so the dates are checked
     * against the year before anything is written.
     *
     * @param array{
     *     academic_year_id: int,
     *     name: string,
     *     sequence: int,
     *     starts_on: string,
     *     ends_on: string,
     *     status?: string
     * } $data
     */
    public function create(User $user, array $data): Term;

    /**
     * @param array{
     *     name?: string,
     *     sequence?: int,
     *     starts_on?: string,
     *     ends_on?: string,
     *     status?: string
     * } $data
     */
    public function update(int $id, User $user, array $data): Term;

    /**
     * Open this term and close whichever other term is running in the same year.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function setActive(int $id, User $user): Term;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function delete(int $id, User $user): void;
}