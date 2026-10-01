<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A course unit or subject (ACD-04).
 *
 * This is the anchor a teacher registers: reference material and marking guides
 * attach here, and examinations, enrolments and marks all reference it. It hangs
 * off an org unit for structure but is a separate entity because it carries
 * credit and teacher assignments.
 */
class CourseUnit extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'institution_id',
        'owner_user_id',
        'org_unit_id',
        'code',
        'title',
        'description',
        'credit_units',
        'level',
        'is_active',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credit_units' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    /**
     * Teachers and lecturers attached to this course.
     */
    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_unit_teachers')
            ->withPivot('is_responsible')
            ->withTimestamps();
    }

    /**
     * Uploaded past papers, marking guides and scheme documents.
     */
    public function resources(): HasMany
    {
        return $this->hasMany(CourseResource::class);
    }

    /**
     * Only the resources a teacher should see by default: the current version
     * of each document. Superseded versions stay queryable for audit.
     */
    public function currentResources(): HasMany
    {
        return $this->resources()->where('is_current', true);
    }

    /**
     * The lecturer accountable for the course, used as the default marker.
     */
    public function responsibleTeacher(): ?User
    {
        $pivot = $this->teachers()->get()->firstWhere('pivot.is_responsible', true);

        return $pivot;
    }
}
