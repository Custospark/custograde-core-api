<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Academic year (ACD-03).
 *
 * Every examination hangs off a term, and a term off a year, so term status can
 * drive examination, archive and billing logic.
 */
class AcademicYear extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'institution_id',
        'owner_user_id',
        'name',
        'starts_on',
        'ends_on',
        'is_current',
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
            'is_current' => 'boolean',
        ];
    }

    public function terms(): HasMany
    {
        return $this->hasMany(Term::class);
    }

    public function currentTerm(): HasOne
    {
        return $this->hasOne(Term::class)->where('status', Term::STATUS_ACTIVE);
    }

    /**
     * A year is usable only when it actually spans time. A year that ends before
     * it starts would silently produce terms outside any year.
     */
    public function hasValidDates(): bool
    {
        return $this->ends_on->greaterThanOrEqualTo($this->starts_on);
    }
}
