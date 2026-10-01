<?php

namespace App\Repositories;

use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\User;
use App\Repositories\Contracts\ExamRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ExamRepository implements ExamRepositoryInterface
{
    public function visibleTo(User $user): Collection
    {
        return Exam::query()
            ->visibleTo($user)
            ->orderByDesc('exam_date')
            ->orderBy('title')
            ->get();
    }

    public function findVisible(int $id, User $user): ?Exam
    {
        return Exam::query()
            ->visibleTo($user)
            ->whereKey($id)
            ->first();
    }

    public function withQuestions(int $id, User $user): ?Exam
    {
        return Exam::query()
            ->visibleTo($user)
            ->with(['questions', 'courseUnit', 'term'])
            ->withCount('students')
            ->whereKey($id)
            ->first();
    }

    public function forCourseUnit(int $courseUnitId, User $user): Collection
    {
        return Exam::query()
            ->visibleTo($user)
            ->where('course_unit_id', $courseUnitId)
            ->orderByDesc('exam_date')
            ->orderBy('title')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Exam
    {
        return Exam::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Exam $exam, array $attributes): Exam
    {
        $exam->fill($attributes)->save();

        return $exam->refresh();
    }

    public function delete(Exam $exam): void
    {
        $exam->delete();
    }

    public function recalculateTotals(Exam $exam): Exam
    {
        // Read through ExamQuestion rather than the ordered relation, because an
        // aggregate with an order by is not portable across the databases this
        // runs on and the order is irrelevant to a sum.
        $totals = ExamQuestion::query()
            ->where('exam_id', $exam->id)
            ->selectRaw('COALESCE(SUM(max_mark), 0) as aggregate_total')
            ->selectRaw('COUNT(*) as aggregate_count')
            ->first();

        $exam->total_marks = (int) round((float) ($totals->aggregate_total ?? 0));
        $exam->question_count = (int) ($totals->aggregate_count ?? 0);
        $exam->save();

        return $exam->refresh();
    }
}