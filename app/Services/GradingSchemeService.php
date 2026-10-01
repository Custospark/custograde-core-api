<?php

namespace App\Services;

use App\Models\GradingScheme;
use App\Models\User;
use App\Repositories\Contracts\GradingSchemeRepositoryInterface;
use App\Services\Contracts\GradingSchemeServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Grading schemes and their bands (ACD-05).
 *
 * The band set is the part that needs defending. A percentage that falls in no
 * band cannot be graded at all, and one that falls in two bands can be graded
 * either way, so a set is accepted or refused as a whole rather than band by
 * band. The refusal lists every problem, because fixing three gaps and then
 * discovering a fourth is not a useful correction path.
 */
class GradingSchemeService implements GradingSchemeServiceInterface
{
    public function __construct(private GradingSchemeRepositoryInterface $schemes) {}

    public function list(User $user): Collection
    {
        return $this->schemes->visibleTo($user);
    }

    public function find(int $id, User $user): GradingScheme
    {
        $scheme = $this->schemes->findVisible($id, $user);

        if ($scheme === null) {
            throw ValidationException::withMessages([
                'id' => 'We could not find that grading scheme in your institution. It may have been removed, or it may belong to another school.',
            ]);
        }

        return $scheme;
    }

    public function create(User $user, array $data): GradingScheme
    {
        $this->assertNameIsFree($user, $data['name']);
        $this->assertPassMarkIsSane($data['pass_mark']);
        // Both dates are optional. A scheme with no effective_from is in force
        // from the start of the current term, which is the common case.
        $this->assertDatesAreSane(
            $data['effective_from'] ?? null,
            $data['effective_to'] ?? null
        );

        $attributes = [
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'pass_mark' => $data['pass_mark'],
            'effective_from' => $data['effective_from'] ?? null,
            'effective_to' => $data['effective_to'] ?? null,
            'is_default' => $data['is_default'] ?? false,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ];

        return DB::transaction(function () use ($user, $attributes, $data) {
            if ($attributes['is_default']) {
                $this->clearDefaultOnOthers($user);
            }

            $model = new GradingScheme;
            $model->assignTenant($user, $attributes);

            $scheme = $this->schemes->create($attributes);

            if (isset($data['bands'])) {
                $this->replaceBands($scheme->id, $user, $data['bands']);
            }

            return $scheme->refresh();
        });
    }

    public function update(int $id, User $user, array $data): GradingScheme
    {
        $scheme = $this->find($id, $user);

        if (array_key_exists('name', $data)) {
            $this->assertNameIsFree($user, $data['name'], $scheme->id);
        }

        if (array_key_exists('pass_mark', $data)) {
            $this->assertPassMarkIsSane($data['pass_mark']);
        }

        $this->assertDatesAreSane(
            $data['effective_from'] ?? $scheme->effective_from?->toDateString(),
            array_key_exists('effective_to', $data) ? $data['effective_to'] : $scheme->effective_to?->toDateString(),
        );

        $attributes = array_filter(
            [
                'name' => array_key_exists('name', $data) ? trim((string) $data['name']) : null,
                'description' => array_key_exists('description', $data) ? $data['description'] : null,
                'pass_mark' => $data['pass_mark'] ?? null,
                'effective_from' => $data['effective_from'] ?? null,
                'effective_to' => array_key_exists('effective_to', $data) ? $data['effective_to'] : null,
                'is_default' => array_key_exists('is_default', $data) ? (bool) $data['is_default'] : null,
                'updated_by' => $user->id,
            ],
            fn (mixed $value, string $key): bool => $key === 'updated_by' || $value !== null,
            ARRAY_FILTER_USE_BOTH
        );

        return DB::transaction(function () use ($user, $scheme, $attributes) {
            if (($attributes['is_default'] ?? false) === true) {
                $this->clearDefaultOnOthers($user, $scheme->id);
            }

            return $this->schemes->update($scheme, $attributes);
        });
    }

    public function delete(int $id, User $user): void
    {
        $scheme = $this->find($id, $user);

        $this->schemes->delete($scheme);
    }

    public function replaceBands(int $id, User $user, array $bands): Collection
    {
        $scheme = $this->find($id, $user);

        if ($bands === []) {
            throw ValidationException::withMessages([
                'bands' => 'A grading scheme needs at least one band, otherwise no mark can be given a grade.',
            ]);
        }

        $this->assertBandsAreOrdered($bands);
        $this->assertBandsCoverEverything($bands);

        return DB::transaction(function () use ($user, $scheme, $bands) {
            $this->schemes->update($scheme, ['updated_by' => $user->id]);

            return $this->schemes->replaceBands($scheme, $bands);
        });
    }

    /**
     * A band that starts above where it ends is a typo rather than a design, and
     * it is caught with the grade named so the teacher knows which row to fix.
     */
    private function assertBandsAreOrdered(array $bands): void
    {
        $problems = [];

        foreach (array_values($bands) as $band) {
            if ((float) $band['min_percent'] > (float) $band['max_percent']) {
                $problems[] = sprintf(
                    'The band for grade %s runs from %s%% up to %s%%, so its lowest mark is higher than its highest. Please swap the two numbers.',
                    $band['grade'],
                    rtrim(rtrim((string) $band['min_percent'], '0'), '.'),
                    rtrim(rtrim((string) $band['max_percent'], '0'), '.')
                );
            }
        }

        if ($problems !== []) {
            throw ValidationException::withMessages(['bands' => $problems]);
        }
    }

    /**
     * The overlap and gap check itself (ACD-05). The model owns the wording
     * because it owns the arithmetic, and every problem it reports is shown
     * rather than only the first.
     */
    private function assertBandsCoverEverything(array $bands): void
    {
        $problems = GradingScheme::bandProblems($bands);

        if ($problems === []) {
            return;
        }

        throw ValidationException::withMessages([
            'bands' => array_merge(
                ['Your grade bands do not cover every mark from 0% to 100%. Please fix each of these:'],
                $problems
            ),
        ]);
    }

    private function assertNameIsFree(User $user, string $name, ?int $exceptId = null): void
    {
        $name = trim($name);

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'Please give the grading scheme a name, for example "Uganda Secondary Grading".',
            ]);
        }

        if (! $this->schemes->nameExistsFor($user, $name, $exceptId)) {
            return;
        }

        throw ValidationException::withMessages([
            'name' => sprintf(
                'You already have a grading scheme called "%s". Please use a different name so results can be traced back to the right scheme.',
                $name
            ),
        ]);
    }

    private function assertPassMarkIsSane(float|int|string $passMark): void
    {
        $value = (float) $passMark;

        if ($value < 0 || $value > 100) {
            throw ValidationException::withMessages([
                'pass_mark' => sprintf(
                    'The pass mark has to be a percentage between 0 and 100, and %s is not. Please enter it as a number in that range.',
                    rtrim(rtrim((string) $value, '0'), '.')
                ),
            ]);
        }
    }

    private function assertDatesAreSane(?string $from, ?string $to): void
    {
        if ($from === null || $to === null) {
            return;
        }

        if (strtotime($to) <= strtotime($from)) {
            throw ValidationException::withMessages([
                'effective_to' => sprintf(
                    'This grading scheme runs from %s, so it has to end after that date. An end date of %s is not usable.',
                    $from,
                    $to
                ),
            ]);
        }
    }

    /**
     * One default per tenant, written in the same transaction as the new default
     * so a teacher is never left with two schemes both claiming to be the one
     * applied automatically.
     */
    private function clearDefaultOnOthers(User $user, ?int $exceptId = null): void
    {
        $this->schemes->visibleTo($user)
            ->filter(fn (GradingScheme $scheme): bool => $scheme->is_default && $scheme->id !== $exceptId)
            ->each(fn (GradingScheme $scheme) => $this->schemes->update($scheme, ['is_default' => false]));
    }
}