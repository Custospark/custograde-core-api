<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Who sits which exam (STU-03).
 *
 * This exists as a model rather than a bare pivot so a candidate can be asked
 * which papers they sat, which a pivot table cannot answer on its own.
 *
 * A script may exist for a candidate with no enrolment here. IDN-06 covers the
 * candidate whose registration could not be read off the paper, and their
 * script still has to be markable and attributable afterwards.
 */
class ExamEnrolment extends Model
{
    protected $fillable = [
        'exam_id',
        'student_id',
        'enrolled_by',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enrolledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }
}