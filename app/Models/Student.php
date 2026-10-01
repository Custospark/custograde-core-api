<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A candidate on the roster (STU-01, STU-03, STU-05).
 *
 * reg_no is unique within the institution rather than globally, because two
 * schools may both have a candidate numbered 0042 and neither can be asked to
 * renumber to suit a system they do not own.
 *
 * The roster is deliberately separate from User: a candidate in a school exam
 * never holds an account, and staff who sit an exam hold both without the two
 * rows being the same thing.
 */
class Student extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** Sitting normally. */
    public const STATUS_ACTIVE = 'active';

    /** Registered but sitting a later sitting; results are held, not absent. */
    public const STATUS_DEFERRED = 'deferred';

    /** Left after registering. Kept so their scripts remain attributable. */
    public const STATUS_WITHDRAWN = 'withdrawn';

    /** Completed the programme; results stay queryable. */
    public const STATUS_GRADUATED = 'graduated';

    /**
     * @var list<string>
     */
    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_DEFERRED,
        self::STATUS_WITHDRAWN,
        self::STATUS_GRADUATED,
    ];

    protected $fillable = [
        'institution_id',
        'owner_user_id',
        'reg_no',
        'first_name',
        'last_name',
        'class_name',
        'phone',
        'email',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * No casts are needed here. Names are stored as given rather than split
     * into a first/middle/last shape, because the schools that enter this data
     * do not agree on how many name parts exist.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }

    /**
     * Which exams this candidate sat. Routed through the pivot because
     * enrolment is the fact being asked about, not the pair of ids.
     */
    public function enrolments(): HasMany
    {
        return $this->hasMany(ExamEnrolment::class);
    }

    /**
     * Scripts captured against exams this candidate was enrolled for, whether
     * or not the enrolment record still exists. IDN-06 attaches a script whose
     * candidate could not be read, and dropping those from the roster view
     * would hide exactly the case an examiner needs to see.
     */
    public function scripts(): HasMany
    {
        return $this->hasMany(Script::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(Result::class);
    }

    public function fullName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * For marker screens under blind marking, where a shape is enough to tell
     * two stacks of papers apart without naming anyone.
     */
    public function initials(): string
    {
        return strtoupper(
            mb_substr((string) $this->first_name, 0, 1)
            . mb_substr((string) $this->last_name, 0, 1)
        );
    }

    /**
     * Only an active candidate should be pulled into a new enrolment list.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}