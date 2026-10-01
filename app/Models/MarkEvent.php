<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in a mark's history (REV-13).
 *
 * This table is append-only by design. Every change to a mark writes a row
 * rather than overwriting the last one, and the application database user is
 * granted no UPDATE or DELETE on it, so a mark's history cannot be rewritten
 * even by a bug in our own code. REV-05 needs a candidate to be able to see how
 * their mark arrived at, and that promise is only worth making if the trail
 * cannot be tidied afterwards.
 *
 * There is therefore no updated_at: an update would mean the history had been
 * edited, which is the one thing this table must never record.
 */
class MarkEvent extends Model
{
    use HasFactory;

    /** @var bool */
    public $timestamps = false;

    public const CREATED_AT = 'created_at';

    protected $fillable = [
        'script_answer_id',
        'script_id',
        'previous_value',
        'new_value',
        'source',
        'reason',
        'actor_id',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'previous_value' => 'decimal:2',
            'new_value' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function scriptAnswer(): BelongsTo
    {
        return $this->belongsTo(ScriptAnswer::class);
    }

    public function script(): BelongsTo
    {
        return $this->belongsTo(Script::class);
    }

    /**
     * Null where the change was made by a machine, which is recorded as source
     * instead: the machine proposed, a person decided.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}