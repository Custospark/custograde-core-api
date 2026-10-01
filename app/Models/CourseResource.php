<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reference document attached to a course unit (ACD-02, EXM-03).
 *
 * Past papers, marking guides, scheme of work. The teacher uploads the document
 * once and every examination built on the course can use it, which is why this
 * sits at course level rather than on the examination.
 *
 * EXM-07 requires guides to be versioned, because a guide may legitimately
 * change after marking has begun. Re-uploading supersedes the previous version
 * rather than overwriting it, so earlier results stay re-derivable and the
 * change is visible rather than silent.
 */
class CourseResource extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** A previous question paper, kept for reference and practice. */
    public const KIND_PAST_PAPER = 'past_paper';

    /** The mark scheme the AI proposes against and a teacher marks by. */
    public const KIND_MARKING_GUIDE = 'marking_guide';

    /** Scheme of work, syllabus, curriculum coverage. */
    public const KIND_SYLLABUS = 'syllabus';

    /** Lesson notes or worked examples. */
    public const KIND_NOTES = 'notes';

    /** Grading scheme or grade boundaries applied to this course. */
    public const KIND_GRADING_SCHEME = 'grading_scheme';

    public const KIND_OTHER = 'other';

    /**
     * @var list<string>
     */
    public const KINDS = [
        self::KIND_PAST_PAPER,
        self::KIND_MARKING_GUIDE,
        self::KIND_SYLLABUS,
        self::KIND_NOTES,
        self::KIND_GRADING_SCHEME,
        self::KIND_OTHER,
    ];

    /**
     * Formats EXM-03 accepts for a marking guide.
     *
     * @var list<string>
     */
    public const ACCEPTED_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain',
    ];

    protected $fillable = [
        'institution_id',
        'course_unit_id',
        'owner_user_id',
        'title',
        'description',
        'kind',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'version',
        'is_current',
        'uploaded_by',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'version' => 'integer',
            'is_current' => 'boolean',
        ];
    }

    public function courseUnit(): BelongsTo
    {
        return $this->belongsTo(CourseUnit::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isMarkingGuide(): bool
    {
        return $this->kind === self::KIND_MARKING_GUIDE;
    }
}
