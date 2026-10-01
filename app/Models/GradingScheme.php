<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A grading scheme (ACD-05): a set of bands mapping a percentage to a grade.
 *
 * MRK-04 requires the scheme in force to be recorded with a result, so this
 * carries effective dates and is superseded rather than edited in place.
 */
class GradingScheme extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /**
     * How close two band boundaries must be to count as touching.
     *
     * Teachers naturally type 0 to 49.99, then 50 to 59.99, because bands are
     * inclusive at both ends and 49.99 + 0.01 is fiddly. A tenth of a percent
     * of slack absorbs that habit while still catching a real gap such as 60 to
     * 70, which is what ACD-05 asks the editor to reject.
     */
    public const BAND_TOLERANCE = 0.1;

    protected $fillable = [
        'institution_id',
        'owner_user_id',
        'name',
        'description',
        'pass_mark',
        'effective_from',
        'effective_to',
        'is_default',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pass_mark' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_default' => 'boolean',
        ];
    }

    public function bands(): HasMany
    {
        return $this->hasMany(GradeBand::class)->orderBy('sort_order');
    }

    /**
     * The grade for a percentage, or null when the bands do not cover it.
     *
     * Bands are contiguous by convention, so the first match is the answer.
     * A gap returns null rather than the nearest band, because silently grading
     * a score into the wrong band is exactly what an exam board would reject.
     */
    public function gradeFor(float $percent): ?GradeBand
    {
        return $this->bands->first(
            fn (GradeBand $band): bool => $percent >= (float) $band->min_percent
                && $percent <= (float) $band->max_percent
        );
    }

    public function isPass(float $percent): bool
    {
        return $percent >= (float) $this->pass_mark;
    }

    /**
     * ACD-05 requires the editor to reject overlaps and gaps, which a database
     * constraint cannot express meaningfully across decimal boundaries. This is
     * the check the service calls before accepting a set of bands.
     *
     * @param  Collection<int, GradeBand>|list<array{min_percent: float|string, max_percent: float|string}>  $bands
     * @return list<string>
     */
    public static function bandProblems($bands): array
    {
        $problems = [];
        $normalised = [];

        foreach ($bands as $band) {
            // Accepts both Eloquent models and the raw validated array, because
            // the service checks a set before it has created any models.
            $min = (float) (is_array($band) ? $band['min_percent'] : $band->min_percent);
            $max = (float) (is_array($band) ? $band['max_percent'] : $band->max_percent);
            $grade = is_array($band) ? ($band['grade'] ?? '?') : $band->grade;

            if ($min > $max) {
                $problems[] = sprintf('The band for %s starts at %.2f%% and ends at %.2f%%.', $grade, $min, $max);
                continue;
            }

            if ($min < 0 || $max > 100) {
                $problems[] = sprintf('The band for %s must sit between 0%% and 100%%.', $grade);
                continue;
            }

            $normalised[] = ['grade' => $grade, 'min' => $min, 'max' => $max];
        }

        usort($normalised, fn (array $a, array $b): int => $a['min'] <=> $b['min']);

        $previousMax = null;
        foreach ($normalised as $band) {
            if ($previousMax === null) {
                if ($band['min'] > 0.0) {
                    $problems[] = sprintf('No grade is defined between 0%% and %.2f%%.', $band['min']);
                }
            } elseif (round($band['min'] - $previousMax, 2) > self::BAND_TOLERANCE) {
                if ($band['min'] > $previousMax) {
                    $problems[] = sprintf(
                        'There is a gap between %.2f%% and %.2f%% with no grade defined.',
                        $previousMax,
                        $band['min']
                    );
                } else {
                    $problems[] = sprintf(
                        'The band for %s overlaps the band above it at %.2f%%.',
                        $band['grade'],
                        $band['min']
                    );
                }
            }

            $previousMax = $band['max'];
        }

        if ($previousMax !== null && $previousMax < 100.0) {
            $problems[] = sprintf('No grade is defined between %.2f%% and 100%%.', $previousMax);
        }

        return $problems;
    }
}
