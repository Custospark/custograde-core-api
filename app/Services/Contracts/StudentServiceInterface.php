<?php

namespace App\Services\Contracts;

use App\Models\Exam;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * The candidate roster and who sits which paper (STU-01, STU-03).
 *
 * The roster is separate from User because a candidate in a school exam never
 * holds an account. Nothing here touches authentication; this service decides
 * only what a teacher may register and who may be entered for a paper.
 */
interface StudentServiceInterface
{
    /**
     * The roster, optionally narrowed to one paper, to a class, or to candidates
     * matching a registration number or name.
     *
     * The narrowing happens in memory rather than in SQL. STU-04 replaces this
     * with indexed server-side pagination once a school has more candidates than
     * fit in a request, and the shape of the call will not change when it does.
     *
     * @return Collection<int, Student>
     */
    public function list(
        User $user,
        ?int $examId = null,
        ?string $search = null,
        ?string $className = null,
    ): Collection;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function find(int $id, User $user): Student;

    /**
     * @param array{
     *     reg_no: string,
     *     first_name: string,
     *     last_name: string,
     *     class_name?: string|null,
     *     phone?: string|null,
     *     email?: string|null,
     *     status?: string
     * } $data
     */
    public function create(User $user, array $data): Student;

    /**
     * @param array<string, mixed> $data
     */
    public function update(User $user, Student $student, array $data): Student;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function enrol(User $user, Exam $exam, int $studentId): void;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function unenrol(User $user, Exam $exam, int $studentId): void;
}