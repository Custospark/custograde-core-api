<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One band in a grading scheme (ACD-05).
 *
 * A contiguous percentage range mapping to a grade, an optional grade point and
 * an optional remark shown on the mark sheet.
 */
class GradeBand extends Model
{
    use HasFactory;

    protected $fillable = [
        'grading_scheme_id',
        'grade',
        'grade_point',
        'remark',
        'min_percent',
        'max_percent',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grade_point' => 'decimal:2',
            'min_percent' => 'decimal:2',
            'max_percent' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function gradingScheme(): BelongsTo
    {
        return $this->belongsTo(GradingScheme::class);
    }

    public function covers(float $percent): bool
    {
        return $percent >= (float) $this->min_percent && $percent <= (float) $this->max_percent;
    }
}
