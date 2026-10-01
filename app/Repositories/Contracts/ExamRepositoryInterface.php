<?php

namespace App\Repositories\Contracts;

use App\Models\Exam;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Data access for examination papers (EXM-01, EXM-02, EXM-06).
 *
 * Every read is tenant-scoped at this layer rather than in the controller, so a
 * forgotten `visibleTo` cannot become a cross-tenant leak.
 */
interface ExamRepositoryInterface
{
    /**
     * @return Collection<int, Exam>
     */
    public function visibleTo(User $user): Collection;

    public function findVisible(int $id, User $user): ?Exam;

    /**
     * One paper with everything a detail screen reads in a single query:
     * its questions in number order, the course it sits under, the term it
     * belongs to, and a count of who is enrolled.
     */
    public function withQuestions(int $id, User $user): ?Exam;

    /**
     * Papers sitting on one course.
     *
     * Ordered newest first, because a teacher's list is a working list where the
     * paper they touched yesterday is the one they are looking for.
     *
     * @return Collection<int, Exam>
     */
    public function forCourseUnit(int $courseUnitId, User $user): Collection;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Exam;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Exam $exam, array $attributes): Exam;

    public function delete(Exam $exam): void;

    /**
     * Rewrite the denormalised paper totals from its questions.
     *
     * total_marks is the sum of the question maxima and question_count is how
     * many there are, so a list view can show both without a join and MRK-02 can
     * refuse a mark above the paper total. Called after every question write.
     */
    public function recalculateTotals(Exam $exam): Exam;
}