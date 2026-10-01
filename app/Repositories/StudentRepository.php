<?php

namespace App\Repositories;

use App\Models\Exam;
use App\Models\Student;
use App\Models\User;
use App\Repositories\Contracts\StudentRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class StudentRepository implements StudentRepositoryInterface
{
    public function visibleTo(User $user): Collection
    {
        return Student::query()
            ->visibleTo($user)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    public function findVisible(int $id, User $user): ?Student
    {
        return Student::query()
            ->visibleTo($user)
            ->whereKey($id)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Student
    {
        return Student::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Student $student, array $attributes): Student
    {
        $student->fill($attributes)->save();

        return $student->refresh();
    }

    public function regNoExistsFor(User $user, string $regNo, ?int $exceptId = null): bool
    {
        $query = Student::query()
            ->visibleTo($user)
            ->where('reg_no', $regNo);

        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }

        return $query->exists();
    }

    public function enrollInExam(Exam $exam, int $studentId, int $actorId): void
    {
        $exam->students()->syncWithoutDetaching([
            $studentId => ['enrolled_by' => $actorId],
        ]);
    }

    public function unenroll(Exam $exam, int $studentId): void
    {
        $exam->students()->detach($studentId);
    }

    public function forExam(Exam $exam): Collection
    {
        return $exam->students()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    public function findByRegNo(User $user, string $regNo): ?Student
    {
        return Student::query()
            ->visibleTo($user)
            ->where('reg_no', $regNo)
            ->first();
    }
}