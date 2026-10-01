<?php

namespace App\Repositories\Contracts;

use App\Models\Exam;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Data access for the candidate roster (STU-01, STU-03, STU-04).
 *
 * The roster is tenant-owned, so every read here is scoped to the acting user.
 * Enrolment is reached through the exam rather than through the candidate,
 * because who sits which paper is the fact being recorded.
 */
interface StudentRepositoryInterface
{
    /**
     * @return Collection<int, Student>
     */
    public function visibleTo(User $user): Collection;

    public function findVisible(int $id, User $user): ?Student;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Student;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Student $student, array $attributes): Student;

    /**
     * True when this tenant already uses this registration number.
     *
     * Checked in the service so the error can be a readable message rather than
     * a raw unique-constraint violation on (institution_id, reg_no).
     */
    public function regNoExistsFor(User $user, string $regNo, ?int $exceptId = null): bool;

    /**
     * Record that a candidate sits this paper.
     *
     * syncWithoutDetaching rather than attach, so re-posting the same enrolment
     * is harmless at this layer. Whether it is allowed at all is the service's
     * call, because the refusal has to read like an explanation.
     */
    public function enrollInExam(Exam $exam, int $studentId, int $actorId): void;

    public function unenroll(Exam $exam, int $studentId): void;

    /**
     * The roster of one paper.
     *
     * @return Collection<int, Student>
     */
    public function forExam(Exam $exam): Collection;

    public function findByRegNo(User $user, string $regNo): ?Student;
}