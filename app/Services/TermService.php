<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Term;
use App\Models\User;
use App\Repositories\Contracts\AcademicYearRepositoryInterface;
use App\Repositories\Contracts\TermRepositoryInterface;
use App\Services\Contracts\TermServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Terms inside an academic year (ACD-03).
 *
 * A year may have only one running term at a time, so opening a term writes two
 * rows. That pair is what lives in a transaction here: a half-applied swap
 * would leave the calendar with two open terms and no way to tell which one
 * examinations belong to.
 */
class TermService implements TermServiceInterface
{
    public function __construct(
        private TermRepositoryInterface $terms,
        private AcademicYearRepositoryInterface $academicYears,
    ) {}

    public function list(User $user, ?int $academicYearId = null): Collection
    {
        if ($academicYearId === null) {
            return $this->terms->visibleTo($user);
        }

        return $this->terms->forYear($academicYearId, $user);
    }

    public function find(int $id, User $user): Term
    {
        $term = $this->terms->findVisible($id, $user);

        if ($term === null) {
            throw ValidationException::withMessages([
                'id' => 'We could not find that term in your institution. It may have been removed, or it may belong to another school.',
            ]);
        }

        return $term;
    }

    public function create(User $user, array $data): Term
    {
        $year = $this->yearOf($user, $data['academic_year_id']);

        $this->assertNameIsFree($user, $data['name']);
        $this->assertSequenceIsFree($user, $year->id, (int) $data['sequence']);
        $this->assertDatesAreSane($data['starts_on'], $data['ends_on']);

        $status = $data['status'] ?? Term::STATUS_PLANNED;

        $candidate = $this->hydrate($data['name'], (int) $data['sequence'], $data['starts_on'], $data['ends_on'], $status);
        $this->assertSitsInsideYear($candidate, $year);

        if ($status === Term::STATUS_ACTIVE) {
            $this->assertNoOtherActiveTerm($user, $year->id);
        }

        $attributes = [
            'academic_year_id' => $year->id,
            'name' => $data['name'],
            'sequence' => (int) $data['sequence'],
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'status' => $status,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ];

        $model = new Term;
        $model->assignTenant($user, $attributes);

        return $this->terms->create($attributes);
    }

    public function update(int $id, User $user, array $data): Term
    {
        $term = $this->find($id, $user);

        return DB::transaction(function () use ($term, $user, $data) {
            $year = $this->yearOf($user, $term->academic_year_id);

            if (array_key_exists('name', $data)) {
                $this->assertNameIsFree($user, $data['name'], $term->id);
            }

            if (array_key_exists('sequence', $data)) {
                $this->assertSequenceIsFree($user, $year->id, (int) $data['sequence'], $term->id);
            }

            $startsOn = $data['starts_on'] ?? $term->starts_on?->toDateString();
            $endsOn = $data['ends_on'] ?? $term->ends_on?->toDateString();

            $this->assertDatesAreSane($startsOn, $endsOn);

            $status = $data['status'] ?? $term->status;
            $candidate = $this->hydrate(
                $data['name'] ?? $term->name,
                (int) ($data['sequence'] ?? $term->sequence),
                $startsOn,
                $endsOn,
                $status,
            );

            $this->assertSitsInsideYear($candidate, $year);

            $updated = $this->terms->update($term, array_filter([
                'name' => $data['name'] ?? null,
                'sequence' => array_key_exists('sequence', $data) ? (int) $data['sequence'] : null,
                'starts_on' => $data['starts_on'] ?? null,
                'ends_on' => $data['ends_on'] ?? null,
                'status' => $status,
                'updated_by' => $user->id,
            ], fn (mixed $value, string $key): bool => $key === 'updated_by' || $value !== null, ARRAY_FILTER_USE_BOTH));

            if ($status === Term::STATUS_ACTIVE) {
                $this->closeOtherActiveTerms($user, $year->id, $term->id);
            }

            return $updated;
        });
    }

    public function setActive(int $id, User $user): Term
    {
        return DB::transaction(function () use ($id, $user) {
            $term = $this->find($id, $user);

            $this->closeOtherActiveTerms($user, (int) $term->academic_year_id, $term->id);

            return $this->terms->update($term, [
                'status' => Term::STATUS_ACTIVE,
                'updated_by' => $user->id,
            ]);
        });
    }

    public function delete(int $id, User $user): void
    {
        $term = $this->find($id, $user);

        // The examination reference check arrives with the Examination entity
        // (EXM-01). Until then a term is only removable while it is empty of
        // anything this project can attach to it.
        $this->terms->delete($term);
    }

    /**
     * Built in memory rather than persisted so the year containment rule can be
     * checked by the model itself, before a row exists to roll back.
     */
    private function hydrate(string $name, int $sequence, string $startsOn, string $endsOn, string $status): Term
    {
        return new Term([
            'name' => $name,
            'sequence' => $sequence,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'status' => $status,
        ]);
    }

    private function assertDatesAreSane(string $startsOn, string $endsOn): void
    {
        if (strtotime($endsOn) < strtotime($startsOn)) {
            throw ValidationException::withMessages([
                'ends_on' => sprintf(
                    '"%s" ends before it starts. Please check the dates you entered.',
                    $endsOn
                ),
            ]);
        }
    }

    private function assertSitsInsideYear(Term $candidate, AcademicYear $year): void
    {
        $candidate->setRelation('academicYear', $year);

        if ($candidate->sitsWithinYear()) {
            return;
        }

        throw ValidationException::withMessages([
            'starts_on' => sprintf(
                'That term must run inside "%s", which runs from %s to %s. Please keep the term dates within those dates.',
                $year->name,
                $year->starts_on->toDateString(),
                $year->ends_on->toDateString()
            ),
        ]);
    }

    private function assertNameIsFree(User $user, string $name, ?int $exceptId = null): void
    {
        $clash = $this->terms->visibleTo($user)
            ->first(fn (Term $term): bool => strcasecmp($term->name, $name) === 0 && $term->id !== $exceptId);

        if ($clash === null) {
            return;
        }

        throw ValidationException::withMessages([
            'name' => sprintf(
                'You already have a term called "%s". Please use a different name so your calendar stays readable.',
                $name
            ),
        ]);
    }

    private function assertSequenceIsFree(User $user, int $academicYearId, int $sequence, ?int $exceptId = null): void
    {
        if (! $this->terms->sequenceExistsFor($user, $academicYearId, $sequence, $exceptId)) {
            return;
        }

        throw ValidationException::withMessages([
            'sequence' => sprintf(
                'Term number %d is already used in that academic year. Each term needs its own position so they are listed in the right order.',
                $sequence
            ),
        ]);
    }

    private function assertNoOtherActiveTerm(User $user, int $academicYearId): void
    {
        $open = $this->terms->forYear($academicYearId, $user)
            ->first(fn (Term $term): bool => $term->isActive());

        if ($open === null) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => sprintf(
                '"%s" is already the running term in that year. Please close it before opening another one.',
                $open->name
            ),
        ]);
    }

    /**
     * Closing rather than deleting: the previous term keeps its examinations and
     * its results, it simply stops being the open one.
     */
    private function closeOtherActiveTerms(User $user, int $academicYearId, int $keepId): void
    {
        $this->terms->forYear($academicYearId, $user)
            ->filter(fn (Term $term): bool => $term->isActive() && $term->id !== $keepId)
            ->each(fn (Term $term) => $this->terms->update($term, [
                'status' => Term::STATUS_CLOSED,
                'updated_by' => $user->id,
            ]));
    }

    private function yearOf(User $user, int $academicYearId): AcademicYear
    {
        $year = $this->academicYears->findVisible($academicYearId, $user);

        if ($year === null) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'We could not find that academic year in your institution. Please choose a year from your own calendar.',
            ]);
        }

        return $year;
    }
}