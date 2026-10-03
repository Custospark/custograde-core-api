<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One captured answer script (CAP-07, SHT-02, MRK-01).
 *
 * original_disk, original_path and original_hash are written once and never
 * touched again. Annotations and any derived image live in their own columns,
 * so nothing can write back over the scan the candidate handed in, and the hash
 * stays usable as ARC-05 tamper evidence.
 *
 * status is the pipeline the script is in, not a decision. A script reaches
 * locked only once every answer on it has been decided by a person, which is
 * what makes BR-02 structural rather than a convention.
 */
class Script extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /**
     * An answer sheet has been issued and no scan has arrived yet.
     *
     * This sits outside the marking lifecycle on purpose. `uploaded` means a
     * paper exists on disk, whereas `issued` means only a code is in circulation,
     * so treating one as the other would put every un-handed-in script into the
     * transcriber queue with no file to read.
     */
    public const STATUS_ISSUED = 'issued';

    /** Files are on disk but pages have not been cut yet. */
    public const STATUS_UPLOADED = 'uploaded';

    /** OCR is running. Nothing here can be marked yet. */
    public const STATUS_TRANSCRIBING = 'transcribing';

    /** Answers exist and the machine has proposed marks. */
    public const STATUS_SUGGESTED = 'suggested';

    /** A person is working through it. */
    public const STATUS_IN_REVIEW = 'in_review';

    /** Every answer is decided; awaiting the lock. */
    public const STATUS_READY_TO_LOCK = 'ready_to_lock';

    /** Marks are closed. Nothing on this script may change. */
    public const STATUS_LOCKED = 'locked';

    /** Held for a second opinion or an identity problem. */
    public const STATUS_FLAGGED = 'flagged';

    /**
     * @var list<string>
     */
    public const STATUSES = [
        self::STATUS_ISSUED,
        self::STATUS_UPLOADED,
        self::STATUS_TRANSCRIBING,
        self::STATUS_SUGGESTED,
        self::STATUS_IN_REVIEW,
        self::STATUS_READY_TO_LOCK,
        self::STATUS_LOCKED,
        self::STATUS_FLAGGED,
    ];

    /**
     * Codes in circulation with no paper behind them yet (SHT-01).
     *
     * Kept out of BUSY_STATUSES on purpose: a marker opening the queue should not
     * be shown forty scripts that exist only as printed codes, because there is
     * nothing on them to mark.
     *
     * @var list<string>
     */
    public const AWAITING_SCAN_STATUSES = [
        self::STATUS_ISSUED,
    ];

    /**
     * Machine stages. A marker opening a script at these gets nothing useful,
     * so the UI routes them away rather than showing an empty page.
     *
     * @var list<string>
     */
    public const BUSY_STATUSES = [
        self::STATUS_UPLOADED,
        self::STATUS_TRANSCRIBING,
    ];

    /**
     * Marking stages, where a person is or can be acting.
     *
     * @var list<string>
     */
    public const MARKING_STATUSES = [
        self::STATUS_SUGGESTED,
        self::STATUS_IN_REVIEW,
        self::STATUS_READY_TO_LOCK,
    ];

    /** Hours a partially marked script may sit untouched before it is called idle. */
    public const IDLE_HOURS = 24;

    protected $fillable = [
        'institution_id',
        'owner_user_id',
        'exam_id',
        'student_id',
        'code',
        'status',
        'original_disk',
        'original_path',
        'original_hash',
        'original_name',
        'mime_type',
        'size_bytes',
        'page_count',
        'expected_page_count',
        'total_mark',
        'max_mark',
        'annotations',
        'locked_by',
        'locked_at',
        'flagged_by',
        'flag_note',
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
            'page_count' => 'integer',
            'expected_page_count' => 'integer',
            'size_bytes' => 'integer',
            'annotations' => 'array',
            'locked_at' => 'datetime',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * Null for the candidate whose registration could not be read. IDN-06 makes
     * that an expected state, not a data error to reject the upload over.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ScriptAnswer::class)->orderBy('question_number');
    }

    public function markEvents(): HasMany
    {
        return $this->hasMany(MarkEvent::class);
    }

    public function result(): HasOne
    {
        return $this->hasOne(Result::class);
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED || $this->locked_at !== null;
    }

    public function isFlagged(): bool
    {
        return $this->status === self::STATUS_FLAGGED;
    }

    public function isBusy(): bool
    {
        return in_array($this->status, self::BUSY_STATUSES, true);
    }

    public function countUndecided(): int
    {
        return $this->answers()->undecided()->count();
    }

    public function countProposals(): int
    {
        return $this->answers()->proposals()->count();
    }

    /**
     * The lock precondition. A script with a single undecided answer must not
     * be closed, because a closed script is evidence of a completed review.
     */
    public function isComplete(): bool
    {
        return $this->countUndecided() === 0;
    }

    /**
     * Whole percent, rounded rather than truncated, so a script one mark from
     * completion reads as 99 rather than looking finished.
     */
    public function percentComplete(): int
    {
        $total = $this->answers()->count();

        if ($total === 0) {
            return 0;
        }

        return (int) round((($total - $this->countUndecided()) / $total) * 100);
    }

    /**
     * Fewer pages than expected usually means a scan was missed or a bundle was
     * split, and marking from an incomplete script would silently under-award.
     */
    public function hasMissingPages(): bool
    {
        return $this->page_count < $this->expected_page_count;
    }

    /**
     * Plain phrases for the queue screen. Returned as strings rather than codes
     * because the queue is read by an examiner deciding what to pick up next,
     * not by code branching on a risk.
     *
     * @return list<string>
     */
    public function riskLabels(): array
    {
        $labels = [];

        if ($this->student_id === null) {
            $labels[] = 'Candidate not identified yet';
        }

        if ($this->hasMissingPages()) {
            $labels[] = sprintf(
                'Missing pages: %d of %d captured',
                $this->page_count,
                $this->expected_page_count
            );
        }

        if ($this->isBusy()) {
            $labels[] = 'Still being processed';
        } elseif ($this->isIdle()) {
            $labels[] = sprintf('No progress for %d hours', self::IDLE_HOURS);
        }

        if ($this->isFlagged()) {
            $labels[] = 'Flagged for review';
        }

        return $labels;
    }

    /**
     * A script sitting in a marking stage with nothing touched on it. Locked and
     * flagged scripts are excluded because waiting is the correct state for
     * them, not a sign of something gone wrong.
     */
    private function isIdle(): bool
    {
        if (! in_array($this->status, self::MARKING_STATUSES, true)) {
            return false;
        }

        return $this->updated_at !== null
            && $this->updated_at->lt(now()->subHours(self::IDLE_HOURS));
    }
}