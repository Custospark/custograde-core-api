<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An examination paper (EXM-01, EXM-06).
 *
 * The container the whole marking chain hangs off. Questions, enrolments,
 * scripts and results all reference this row, and its status is the gate that
 * decides which of those may still change. Marking cannot begin until the paper
 * is ready, and nothing changes after it is finalised, so the status is treated
 * as more than a label.
 *
 * total_marks and question_count are denormalised onto the paper by ExamService
 * rather than computed per read, because every list view shows a mark total and
 * a count, and both are also needed when checking a mark against AIG-05.
 */
class Exam extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** Sits inside teaching weeks; contributes to the continuous total. */
    public const TYPE_CONTINUOUS_ASSESSMENT = 'continuous_assessment';

    public const TYPE_MID_TERM = 'mid_term';

    /** A full paper sat under examination conditions, not for a grade. */
    public const TYPE_MOCK = 'mock';

    public const TYPE_END_OF_SEMESTER = 'end_of_semester';

    /** Set by an examination body, outside the institution's own control. */
    public const TYPE_NATIONAL = 'national';

    public const TYPE_END_OF_TERM = 'end_of_term';

    /**
     * @var list<string>
     */
    public const TYPES = [
        self::TYPE_CONTINUOUS_ASSESSMENT,
        self::TYPE_MID_TERM,
        self::TYPE_MOCK,
        self::TYPE_END_OF_SEMESTER,
        self::TYPE_NATIONAL,
        self::TYPE_END_OF_TERM,
    ];

    /** Paper is being written; questions and marks may still change freely. */
    public const STATUS_DRAFT = 'draft';

    /** Paper is settled and may sit an exam. EXM-06: marking starts here. */
    public const STATUS_READY = 'ready';

    /** Candidates are writing the paper now. */
    public const STATUS_SAT = 'sat';

    /** Scripts are coming in and being turned into pages. */
    public const STATUS_SCANNING = 'scanning';

    /** Scripts exist and marks are being decided by people. */
    public const STATUS_MARKING = 'marking';

    /** Marks are closed; results exist but nothing has been published. */
    public const STATUS_FINALISED = 'finalised';

    /** Kept for audit. Nothing may be changed. */
    public const STATUS_ARCHIVED = 'archived';

    /**
     * @var list<string>
     */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_READY,
        self::STATUS_SAT,
        self::STATUS_SCANNING,
        self::STATUS_MARKING,
        self::STATUS_FINALISED,
        self::STATUS_ARCHIVED,
    ];

    /**
     * Marking is under way once scripts exist, whatever the scan pipeline is
     * still doing alongside it.
     *
     * @var list<string>
     */
    public const MARKING_STATUSES = [
        self::STATUS_SAT,
        self::STATUS_SCANNING,
        self::STATUS_MARKING,
    ];

    /**
     * No status from which a paper can no longer be edited.
     *
     * @var list<string>
     */
    public const CLOSED_STATUSES = [
        self::STATUS_FINALISED,
        self::STATUS_ARCHIVED,
    ];

    protected $fillable = [
        'institution_id',
        'owner_user_id',
        'course_unit_id',
        'term_id',
        'grading_scheme_id',
        'title',
        'type',
        'exam_date',
        'duration_minutes',
        'total_marks',
        'question_count',
        'blind_marking',
        'results_visible_to_students',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
            'duration_minutes' => 'integer',
            'total_marks' => 'integer',
            'question_count' => 'integer',
            'blind_marking' => 'boolean',
            'results_visible_to_students' => 'boolean',
        ];
    }

    public function courseUnit(): BelongsTo
    {
        return $this->belongsTo(CourseUnit::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function gradingScheme(): BelongsTo
    {
        return $this->belongsTo(GradingScheme::class);
    }

    /**
     * Ordered because a paper is read in number order everywhere it appears.
     */
    public function questions(): HasMany
    {
        return $this->hasMany(ExamQuestion::class)->orderBy('number');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'exam_enrolments')
            ->withPivot('enrolled_by')
            ->withTimestamps();
    }

    public function scripts(): HasMany
    {
        return $this->hasMany(Script::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(Result::class);
    }

    /**
     * STU-07: when this is on, identity is re-linked only at finalisation, so
     * marking screens must not resolve the candidate.
     */
    public function isBlindMarked(): bool
    {
        return $this->blind_marking === true;
    }

    public function isMarkingActive(): bool
    {
        return in_array($this->status, self::MARKING_STATUSES, true);
    }

    public function canEditQuestions(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_READY], true);
    }

    /**
     * Scripts may be attached right up to finalisation, because a late or absent
     * candidate still produces a script afterwards and dropping it would lose
     * the record of why.
     */
    public function canAcceptScripts(): bool
    {
        return ! in_array($this->status, self::CLOSED_STATUSES, true);
    }

    /**
     * Read off the denormalised count first, since that is what every list view
     * already has, and fall back to the relation only when the count has not
     * been recomputed yet.
     */
    public function hasQuestions(): bool
    {
        if ($this->question_count > 0) {
            return true;
        }

        return $this->questions()->exists();
    }
}