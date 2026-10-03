<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\GradeBand;
use App\Models\GradingScheme;
use App\Models\Result;
use App\Models\Script;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Compiling, grading and releasing results (MRK-01 to MRK-10).
 *
 * A result is never typed in and never edited in place. It is computed from
 * locked scripts, converted through a grading scheme whose identity is recorded
 * alongside the result, and versioned so a correction adds a version rather than
 * overwriting the record an institution may already have defended.
 */
class ResultService
{
    /**
     * Compile results for an exam from its locked scripts (MRK-01, MRK-04).
     *
     * @return array{compiled: int, skipped: list<string>}
     */
    public function compileForExam(User $actor, Exam $exam): array
    {
        $locked = $exam->scripts()->where('status', Script::STATUS_LOCKED)->get();

        if ($locked->isEmpty()) {
            throw ValidationException::withMessages([
                'exam' => 'None of the scripts on this examination have been approved yet, so there is nothing to compile. Approve at least one script first.',
            ]);
        }

        $scheme = $exam->gradingScheme;
        $compiled = 0;
        $skipped = [];

        // A result with no grade and no pass mark is not a finished result, it is
        // a number waiting for a decision. Saying so here is the difference
        // between an examinations officer noticing and a parent discovering it.
        if ($scheme === null) {
            throw ValidationException::withMessages([
                'grading_scheme_id' => 'This examination has no grading scheme, so marks cannot be graded yet. '
                    . 'Choose a grading scheme for the paper, then compile again.',
            ]);
        }

        DB::transaction(function () use ($exam, $actor, $scheme, $locked, &$compiled, &$skipped): void {
            foreach ($locked as $script) {
                $result = $this->compileOne($actor, $exam, $script, $scheme);

                if ($result === null) {
                    $skipped[] = $script->code;

                    continue;
                }

                $compiled++;
            }
        });

        return ['compiled' => $compiled, 'skipped' => $skipped];
    }

    /**
     * One script into one result, versioned rather than overwritten (MRK-08).
     */
    private function compileOne(User $actor, Exam $exam, Script $script, ?GradingScheme $scheme): ?Result
    {
        if ($script->max_mark <= 0) {
            return null;
        }

        // A script with no student cannot be attached to a result. It stays in
        // the queue for the exception handler rather than being attached to
        // nobody, which is how a script gets lost (IDN-05).
        $studentId = $script->student_id;
        if ($studentId === null) {
            return null;
        }

        $percentage = round(($script->total_mark / $script->max_mark) * 100, 2);

        $band = $scheme !== null ? $this->bandFor($scheme, $percentage) : null;

        $existing = Result::query()
            ->where('exam_id', $exam->id)
            ->where('student_id', $studentId)
            ->orderByDesc('version')
            ->first();

        // Re-compiling an unchanged result is a no-op, so pressing the button
        // twice does not create a spurious new version.
        if ($existing !== null
            && (float) $existing->total_mark === (float) $script->total_mark
            && $existing->status === Result::STATUS_DRAFT
        ) {
            $existing->update([
                'script_id' => $script->id,
                'max_mark' => $script->max_mark,
                'percentage' => $percentage,
                'grade' => $band?->grade,
                'grade_remark' => $band?->remark,
                'is_pass' => $scheme !== null ? $percentage >= (float) $scheme->pass_mark : null,
                'updated_by' => $actor->id,
            ]);

            return $existing;
        }

        $attributes = [
            'institution_id' => $exam->institution_id,
            'owner_user_id' => $exam->owner_user_id,
            'exam_id' => $exam->id,
            'student_id' => $studentId,
            'script_id' => $script->id,
            'total_mark' => $script->total_mark,
            'max_mark' => $script->max_mark,
            'percentage' => $percentage,
            // The scheme in force is recorded, so the grade can be re-derived
            // even after the scheme itself has been superseded.
            'grading_scheme_id' => $scheme?->id,
            'grade' => $band?->grade,
            'grade_remark' => $band?->remark,
            'is_pass' => $scheme !== null ? $percentage >= (float) $scheme->pass_mark : null,
            'status' => Result::STATUS_FINALISED,
            'version' => $existing === null ? 1 : ((int) $existing->version + 1),
            'finalised_by' => $actor->id,
            'finalised_at' => now(),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ];

        if ($existing !== null) {
            // MRK-08: a correction is a new version. The previous one is kept
            // so an institution can show what a candidate was originally given.
            $attributes['status'] = Result::STATUS_AMENDED;
            $attributes['release_status'] = $existing->release_status;
            $existing->update($attributes);

            return $existing;
        }

        return Result::query()->create($attributes);
    }

    /**
     * Find the band for a percentage, tolerating the small gaps a teacher leaves
     * when typing boundaries.
     */
    private function bandFor(GradingScheme $scheme, float $percentage): ?GradeBand
    {
        $bands = $scheme->bands()->get();

        foreach ($bands as $band) {
            if ($percentage >= (float) $band->min_percent - GradingScheme::BAND_TOLERANCE
                && $percentage <= (float) $band->max_percent + GradingScheme::BAND_TOLERANCE
            ) {
                return $band;
            }
        }

        return null;
    }

    /**
     * Release results to candidates (MRK-09).
     *
     * Withheld by default, because releasing is the institution's decision and
     * an examiner releasing early is a real failure mode.
     */
    public function release(User $actor, Exam $exam): int
    {
        $count = Result::query()
            ->where('exam_id', $exam->id)
            ->where('status', '!=', Result::STATUS_DRAFT)
            ->update([
                'release_status' => Result::RELEASE_RELEASED,
                'status' => Result::STATUS_RELEASED,
                'released_at' => now(),
                'updated_by' => $actor->id,
            ]);

        if ($count === 0) {
            throw ValidationException::withMessages([
                'exam' => 'There are no finalised results on this examination yet, so there is nothing to release. Approve some scripts first.',
            ]);
        }

        $exam->update(['status' => Exam::STATUS_FINALISED, 'updated_by' => $actor->id]);

        return $count;
    }

    /**
     * Withdraw a release without deleting anything (MRK-09).
     */
    public function withhold(User $actor, Exam $exam): int
    {
        return Result::query()
            ->where('exam_id', $exam->id)
            ->update([
                'release_status' => Result::RELEASE_WITHHELD,
                'status' => Result::STATUS_FINALISED,
                'released_at' => null,
                'updated_by' => $actor->id,
            ]);
    }

    /**
     * Class statistics for a paper (MRK-03).
     *
     * @return array<string, mixed>
     */
    public function statistics(Exam $exam): array
    {
        $results = Result::query()
            ->where('exam_id', $exam->id)
            ->where('status', '!=', Result::STATUS_DRAFT)
            ->get(['total_mark', 'max_mark', 'percentage', 'grade', 'is_pass']);

        if ($results->isEmpty()) {
            return [
                'count' => 0,
                'enrolled' => $exam->students()->count(),
                'captured' => $exam->scripts()->count(),
                'locked' => $exam->scripts()->where('status', Script::STATUS_LOCKED)->count(),
            ];
        }

        $percentages = $results->pluck('percentage')->filter(fn ($v): bool => $v !== null)->sort()->values();

        $mean = $percentages->isEmpty() ? 0.0 : round((float) $percentages->avg(), 2);

        // median() on a collection takes a key, not a position, so the
        // middle value is taken by index on the sorted, re-indexed list.
        $median = 0.0;
        if ($percentages->isNotEmpty()) {
            $count = $percentages->count();
            $median = $count % 2 === 0
                ? ((float) $percentages->get((int) ($count / 2) - 1) + (float) $percentages->get((int) ($count / 2))) / 2
                : (float) $percentages->get((int) floor($count / 2));
        }
        $median = round($median, 2);

        // Population standard deviation, which is what a single cohort is.
        $variance = $percentages->count() > 0
            ? $percentages->reduce(function (float $carry, float $value) use ($mean): float {
                return $carry + (($value - $mean) ** 2);
            }, 0.0) / $percentages->count()
            : 0.0;

        return [
            'count' => $results->count(),
            'enrolled' => $exam->students()->count(),
            'captured' => $exam->scripts()->count(),
            'locked' => $exam->scripts()->where('status', Script::STATUS_LOCKED)->count(),
            'mean' => $mean,
            'median' => round($median, 2),
            'std_dev' => round(sqrt($variance), 2),
            'min' => (float) $percentages->min(),
            'max' => (float) $percentages->max(),
            'pass_rate' => (int) round(
                ($results->where('is_pass', true)->count() / max(1, $results->count())) * 100
            ),
            'grade_distribution' => $results->whereNotNull('grade')->groupBy('grade')
                ->map(fn ($group) => $group->count())
                ->sortDesc()
                ->all(),
        ];
    }
}
