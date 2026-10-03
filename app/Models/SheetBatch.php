<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One request to print a whole class of answer sheets (SHT-05).
 *
 * @property int $id
 * @property int $exam_id
 * @property string $status
 * @property int $total
 * @property int $processed
 * @property int $skipped
 * @property int $failed
 * @property string|null $archive_path
 * @property int|null $archive_bytes
 * @property array<int, array{candidate: string, reason: string}>|null $errors
 */
class SheetBatch extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'exam_id',
        'institution_id',
        'status',
        'total',
        'processed',
        'skipped',
        'failed',
        'disk',
        'archive_path',
        'archive_bytes',
        'errors',
        'requested_by',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'processed' => 'integer',
            'skipped' => 'integer',
            'failed' => 'integer',
            'archive_bytes' => 'integer',
            'errors' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true);
    }

    /**
     * An archive is only offered once every candidate has been dealt with.
     *
     * A partially written ZIP would download and look fine, which is worse than
     * no download at all: a teacher would print it believing the class was
     * covered.
     */
    public function hasArchive(): bool
    {
        return $this->isFinished()
            && $this->archive_path !== null
            && $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Progress as a percentage of candidates accounted for.
     *
     * Counts skipped as done, because a candidate who already has a sheet is
     * genuinely dealt with. Reporting them as not started would leave a batch
     * looking permanently stuck for a class that was mostly issued already.
     */
    public function progressPercent(): int
    {
        // Cast rather than compare, because a batch row is created before the
        // candidate count is known, so the attribute is null here rather than 0
        // and a strict comparison sails straight past the guard.
        $total = (int) $this->total;

        if ($total === 0) {
            return $this->isFinished() ? 100 : 0;
        }

        $done = $this->processed + $this->skipped + $this->failed;

        return (int) min(100, round($done / $total * 100));
    }
}