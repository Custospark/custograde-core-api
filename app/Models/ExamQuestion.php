<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One question on a paper (EXM-02, AIG-05).
 *
 * max_mark and granularity sit here rather than on the paper because a mark is
 * validated against the question it belongs to, and a paper total cannot
 * enforce that. Keeping them per question also means a question can be worth a
 * different amount without any special casing on the script.
 *
 * The guide is stored per question as a list of points rather than as prose.
 * AIG-05 measures the machine proposal against those points, and REV-04 requires
 * a teacher to be able to change one point at a time and see what the change
 * did, which prose cannot support.
 */
class ExamQuestion extends Model
{
    use HasFactory;

    /** Answered by selecting a key; markable without reading. */
    public const KIND_MULTIPLE_CHOICE = 'multiple_choice';

    /** A few words or a sentence; the machine proposes against the guide. */
    public const KIND_SHORT_ANSWER = 'short_answer';

    /** A number with units; markable once the reading is trusted. */
    public const KIND_NUMERIC = 'numeric';

    /** An essay or a diagram-bearing answer; always routed to a person. */
    public const KIND_STRUCTURED = 'structured';

    /**
     * @var list<string>
     */
    public const KINDS = [
        self::KIND_MULTIPLE_CHOICE,
        self::KIND_SHORT_ANSWER,
        self::KIND_NUMERIC,
        self::KIND_STRUCTURED,
    ];

    /**
     * Kinds a mark can be settled on from the answer key alone.
     *
     * @var list<string>
     */
    public const OBJECTIVE_KINDS = [
        self::KIND_MULTIPLE_CHOICE,
        self::KIND_NUMERIC,
    ];

    protected $fillable = [
        'exam_id',
        'number',
        'prompt',
        'kind',
        'max_mark',
        'granularity',
        'model_answer',
        'guide_points',
        'options',
        'answer_key',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_mark' => 'decimal:2',
            'granularity' => 'decimal:2',
            'guide_points' => 'array',
            'options' => 'array',
            'answer_key' => 'array',
            'number' => 'integer',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function scriptAnswers(): HasMany
    {
        return $this->hasMany(ScriptAnswer::class, 'question_id');
    }

    /**
     * Whether the answer key alone settles the mark. Anything else is read as
     * text and judged against the guide.
     */
    public function isObjective(): bool
    {
        return in_array($this->kind, self::OBJECTIVE_KINDS, true);
    }

    /**
     * A question may legitimately have no guide yet, and a caller asking for the
     * points should get an empty list to iterate rather than null to handle at
     * every step.
     *
     * @return array<int, mixed>
     */
    public function getGuidePointsAttribute(): array
    {
        $decoded = json_decode((string) ($this->attributes['guide_points'] ?? ''), true);

        return is_array($decoded) ? $decoded : [];
    }
}