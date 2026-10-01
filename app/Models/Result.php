<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A candidate's outcome for one examination (MRK-04, MRK-06, MRK-08, MRK-09).
 *
 * Results are versioned, never overwritten. A correction after the fact writes
 * the next version and keeps the one it replaced, so what a candidate was told
 * on release day remains recoverable even after the record changes.
 *
 * grading_scheme_id is recorded with the row rather than looked up, because the
 * scheme in force at marking time has to stay re-derivable after the scheme
 * itself has been superseded.
 */
class Result extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** Marks are still moving. Not a statement of anything. */
    public const STATUS_DRAFT = 'draft';

    /** Marks are closed and signed off by a person. */
    public const STATUS_FINALISED = 'finalised';

    /** Published to the candidate, subject to release_status. */
    public const STATUS_RELEASED = 'released';

    /** A correction has been issued; the earlier version still exists. */
    public const STATUS_AMENDED = 'amended';

    /**
     * @var list<string>
     */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_FINALISED,
        self::STATUS_RELEASED,
        self::STATUS_AMENDED,
    ];

    /** The candidate cannot see this result yet. */
    public const RELEASE_WITHHELD = 'withheld';

    /** The candidate can see everything. */
    public const RELEASE_RELEASED = 'released';

    /** Grade and total are visible, commentary is not. */
    public const RELEASE_PARTIAL = 'partial';

    /**
     * @var list<string>
     */
    public const RELEASE_STATUSES = [
        self::RELEASE_WITHHELD,
        self::RELEASE_RELEASED,
        self::RELEASE_PARTIAL,
    ];

    protected $fillable = [
        'institution_id',
        'owner_user_id',
        'exam_id',
        'student_id',
        'script_id',
        'grading_scheme_id',
        'total_mark',
        'max_mark',
        'percentage',
        'grade',
        'grade_remark',
        'is_pass',
        'status',
        'release_status',
        'version',
        'finalised_by',
        'finalised_at',
        'released_at',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_mark' => 'decimal:2',
            'max_mark' => 'decimal:2',
            'percentage' => 'decimal:2',
            'is_pass' => 'boolean',
            'version' => 'integer',
            'finalised_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * Null for a candidate identified at finalisation only, and for outcomes
     * recorded for an absent candidate with no script.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function script(): BelongsTo
    {
        return $this->belongsTo(Script::class);
    }

    public function gradingScheme(): BelongsTo
    {
        return $this->belongsTo(GradingScheme::class);
    }

    /**
     * MRK-09: visibility is decided per result, so a whole exam being released
     * does not automatically release every one of its candidates.
     */
    public function isReleased(): bool
    {
        return $this->release_status === self::RELEASE_RELEASED;
    }

    public function isFinalised(): bool
    {
        return in_array(
            $this->status,
            [self::STATUS_FINALISED, self::STATUS_RELEASED, self::STATUS_AMENDED],
            true
        );
    }

    /**
     * A correction is a new version rather than an edit, so this is refused once
     * a candidate has seen the result: changing a released mark in place would
     * make what was published unrecoverable, which MRK-08 forbids.
     */
    public function canBeAmended(): bool
    {
        return $this->isFinalised() && $this->release_status !== self::RELEASE_RELEASED;
    }

    public function gradeLabel(): string
    {
        return trim(($this->grade ?? '') . ' ' . ($this->grade_remark ?? ''));
    }
}