<?php

namespace App\Services\Contracts;

use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Examination papers and their questions (EXM-01, EXM-02, EXM-06).
 *
 * The service owns the two decisions a repository cannot: whether the paper is
 * still open to change, and whether a mark ceiling on the paper still holds. A
 * repository that let either through would report a raw constraint failure to a
 * teacher instead of a sentence they can act on.
 */
interface ExamServiceInterface
{
    /**
     * Papers visible to this user, optionally narrowed to one course.
     *
     * @return Collection<int, Exam>
     */
    public function list(User $user, ?int $courseUnitId = null): Collection;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function find(int $id, User $user): Exam;

    /**
     * @param array{
     *     title: string,
     *     type: string,
     *     course_unit_id: int,
     *     exam_date: string,
     *     term_id?: int|null,
     *     duration_minutes?: int|null,
     *     grading_scheme_id?: int|null,
     *     blind_marking?: bool
     * } $data
     */
    public function create(User $user, array $data): Exam;

    /**
     * @param array<string, mixed> $data
     */
    public function update(User $user, Exam $exam, array $data): Exam;

    /**
     * Refuses while the paper still carries scripts, because deleting it would
     * take the record of a candidate's work with it. Archive instead.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function delete(User $user, Exam $exam): void;

    /**
     * @param array<string, mixed> $data
     */
    public function addQuestion(User $user, Exam $exam, array $data): ExamQuestion;

    /**
     * @param array<string, mixed> $data
     */
    public function updateQuestion(User $user, Exam $exam, int $questionId, array $data): ExamQuestion;

    public function deleteQuestion(User $user, Exam $exam, int $questionId): void;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function setStatus(User $user, Exam $exam, string $status): Exam;

    public function recalculateTotals(Exam $exam): Exam;
}