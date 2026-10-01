<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One answer on one script, and the mark made on it (OCR-01, AIG-01, BR-02).
 *
 * The row carries both halves of the human-in-the-loop rule. suggested_mark is
 * the machine's proposal and is never a mark of record; mark is the mark of
 * record and stays null until a person acts. An answer counts as decided only
 * when mark is set AND approved_by names that person, because either alone
 * would let something slip through: a mark with no approver, or an approver on a
 * mark nobody actually set.
 *
 * machine_text is never overwritten. OCR-04 requires a teacher's correction to
 * sit beside the original reading, so the corrected text is a separate column.
 */
class ScriptAnswer extends Model
{
    use HasFactory;

    /** Nothing has been read off the page yet. */
    public const CONTENT_PENDING = 'pending';

    /** Handwriting or print was read as text. */
    public const CONTENT_TEXT = 'text';

    /** A graph, a diagram or a drawing. Cannot be marked from text. */
    public const CONTENT_NON_TEXT = 'non_text';

    /** The page was inspected and there is genuinely nothing written. */
    public const CONTENT_BLANK = 'blank';

    /**
     * @var list<string>
     */
    public const CONTENT_TYPES = [
        self::CONTENT_PENDING,
        self::CONTENT_TEXT,
        self::CONTENT_NON_TEXT,
        self::CONTENT_BLANK,
    ];

    /** Content types that always need a person, because no machine reading exists. */
    public const HUMAN_CONTENT_TYPES = [
        self::CONTENT_PENDING,
        self::CONTENT_NON_TEXT,
    ];

    /** The key was read optically and the answer matched the key. */
    public const SOURCE_OMR_AUTO = 'omr_auto';

    /** The key was read but the mark is ambiguous; it was never settled. */
    public const SOURCE_OMR_AMBIGUOUS = 'omr_ambiguous';

    /** A machine proposal is waiting on this answer. Not a decision. */
    public const SOURCE_AI_SUGGESTED = 'ai_suggested';

    /** A person took the machine's mark as it stood. */
    public const SOURCE_ACCEPTED = 'accepted';

    /** A person set a different mark, usually after correcting the reading. */
    public const SOURCE_ADJUSTED = 'adjusted';

    /** The proposal was rejected and the mark was set independently. */
    public const SOURCE_REJECTED = 'rejected';

    /** Entered by a person with no proposal in front of them. */
    public const SOURCE_MANUAL = 'manual';

    /** No mark yet, so no source can be claimed. */
    public const SOURCE_PENDING = 'pending';

    /**
     * @var list<string>
     */
    public const SOURCES = [
        self::SOURCE_OMR_AUTO,
        self::SOURCE_OMR_AMBIGUOUS,
        self::SOURCE_AI_SUGGESTED,
        self::SOURCE_ACCEPTED,
        self::SOURCE_ADJUSTED,
        self::SOURCE_REJECTED,
        self::SOURCE_MANUAL,
        self::SOURCE_PENDING,
    ];

    /**
     * Below this, the reading is offered to a marker as a warning rather than
     * relied on. Tuned so that ordinary handwriting sits above it.
     */
    public const LOW_CONFIDENCE_THRESHOLD = 0.80;

    protected $fillable = [
        'script_id',
        'question_id',
        'question_number',
        'machine_text',
        'corrected_text',
        'transcription_confidence',
        'low_confidence_words',
        'content_type',
        'truncated',
        'transcription_note',
        'transcription_model',
        'suggested_mark',
        'suggestion_confidence',
        'suggestion_rationale',
        'matched_points',
        'suggestion_strategy',
        'suggestion_model',
        'suggested_at',
        'mark',
        'mark_source',
        'approved_by',
        'approved_at',
        'reason',
        'feedback',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mark' => 'decimal:2',
            'suggested_mark' => 'decimal:2',
            'transcription_confidence' => 'decimal:2',
            'suggestion_confidence' => 'decimal:2',
            'matched_points' => 'array',
            'low_confidence_words' => 'array',
            'truncated' => 'boolean',
            'suggested_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function script(): BelongsTo
    {
        return $this->belongsTo(Script::class);
    }

    /**
     * Null where the question was deleted after the script was captured. The
     * answer survives because the candidate's writing is evidence, even when the
     * question it answered is not.
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(ExamQuestion::class, 'question_id');
    }

    public function markEvents(): HasMany
    {
        return $this->hasMany(MarkEvent::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * The gate for BR-02. Both conditions, because either on its own is
     * reachable: a mark written without anyone naming themselves, or an approver
     * recorded against a mark that was never set.
     */
    public function isDecided(): bool
    {
        return $this->mark !== null && $this->approved_by !== null;
    }

    public function isProposal(): bool
    {
        return $this->suggested_mark !== null && ! $this->isDecided();
    }

    public function isNonText(): bool
    {
        return $this->content_type === self::CONTENT_NON_TEXT;
    }

    public function needsTeacher(): bool
    {
        return in_array($this->content_type, self::HUMAN_CONTENT_TYPES, true);
    }

    public function hasLowConfidenceReading(): bool
    {
        return $this->transcription_confidence !== null
            && (float) $this->transcription_confidence < self::LOW_CONFIDENCE_THRESHOLD;
    }

    /**
     * What a marker should read: the human correction where one exists, the raw
     * machine reading otherwise, never null so callers do not guard.
     */
    public function effectiveText(): string
    {
        return $this->corrected_text ?? $this->machine_text ?? '';
    }

    /**
     * The ceiling for this answer, read through the question because AIG-05
     * validates a mark against the question's own max. Zero when the question is
     * gone, so a missing question cannot make a mark look valid.
     */
    public function maxMark(): float
    {
        return (float) ($this->question?->max_mark ?? 0);
    }

    /**
     * Used by Script::countUndecided, so the two halves of the rule cannot drift
     * apart between a status screen and the lock precondition.
     *
     * @param  Builder<ScriptAnswer>  $query
     * @return Builder<ScriptAnswer>
     */
    public function scopeUndecided(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereNull('mark')->orWhereNull('approved_by');
        });
    }

    /**
     * @param  Builder<ScriptAnswer>  $query
     * @return Builder<ScriptAnswer>
     */
    public function scopeProposals(Builder $query): Builder
    {
        return $query->whereNotNull('suggested_mark')->undecided();
    }
}