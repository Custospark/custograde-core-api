<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Term or semester within an academic year (ACD-03).
 *
 * Uganda secondary schools and most East African universities use three terms;
 * some universities use two semesters per year. The model is deliberately
 * agnostic: the year defines how many, the sequence defines the order.
 */
class Term extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /**
     * A term the calendar has been written for but which has not opened.
     */
    public const STATUS_PLANNED = 'planned';

    /** The term currently running. Examinations attach here. */
    public const STATUS_ACTIVE = 'active';

    /** The term has closed; results are finalised but not necessarily released. */
    public const STATUS_CLOSED = 'closed';

    /**
     * @var list<string>
     */
    public const STATUSES = [
        self::STATUS_PLANNED,
        self::STATUS_ACTIVE,
        self::STATUS_CLOSED,
    ];

    protected $fillable = [
        'academic_year_id',
        'institution_id',
        'owner_user_id',
        'name',
        'sequence',
        'starts_on',
        'ends_on',
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
            'starts_on' => 'date',
            'ends_on' => 'date',
            'sequence' => 'integer',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * The term must sit inside its year, otherwise an examination dated inside
     * the year would attach to a term that claims different dates.
     */
    public function sitsWithinYear(): bool
    {
        $year = $this->academicYear;

        if ($year === null) {
            return false;
        }

        return $this->starts_on->greaterThanOrEqualTo($year->starts_on)
            && $this->ends_on->lessThanOrEqualTo($year->ends_on);
    }
}
