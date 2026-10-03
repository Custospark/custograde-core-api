<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One issued answer sheet (SHT-01, SHT-06).
 *
 * Every issue is a row, including superseded ones, because "which code did this
 * candidate actually sit for" is the question an examination dispute turns on.
 * The current sheet is simply the one with a null `invalidated_at`.
 *
 * @property int $id
 * @property int $script_id
 * @property string $code
 * @property int $page_count
 * @property string|null $invalidation_reason
 */
class ScriptSheet extends Model
{
    use HasFactory;

    protected $fillable = [
        'script_id',
        'institution_id',
        'code',
        'page_count',
        'issued_at',
        'invalidated_at',
        'invalidation_reason',
        'issued_by',
        'invalidated_by',
    ];

    protected function casts(): array
    {
        return [
            'page_count' => 'integer',
            'issued_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }

    public function script(): BelongsTo
    {
        return $this->belongsTo(Script::class);
    }

    /** The sheet a candidate should be using right now. */
    public function scopeCurrent($query)
    {
        return $query->whereNull('invalidated_at');
    }

    public function isCurrent(): bool
    {
        return $this->invalidated_at === null;
    }
}