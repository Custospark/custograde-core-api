<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Student;
use App\Models\User;
use App\Repositories\Contracts\StudentRepositoryInterface;
use App\Services\Contracts\ExamServiceInterface;
use App\Services\Contracts\StudentServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The candidate roster and who sits which paper (STU-01, STU-03).
 *
 * The registration number is unique within the institution rather than globally,
 * because two schools may both have a candidate numbered 0042 and neither can be
 * asked to renumber to suit a system they do not own. That check is done here so
 * the refusal can name the number, instead of surfacing a unique-constraint
 * failure to a teacher mid-data-entry.
 */
class StudentService implements StudentServiceInterface
{
    public function __construct(
        private StudentRepositoryInterface $students,
        private ExamServiceInterface $exams,
    ) {}

    public function list(
        User $user,
        ?int $examId = null,
        ?string $search = null,
        ?string $className = null,
    ): Collection {
        if ($examId !== null) {
            // Resolved through the exam service rather than read directly, so a
            // paper from another school narrows to nothing visible rather than
            // listing that school's roster under a guessed id.
            return $this->students->forExam($this->exams->find($examId, $user));
        }

        $students = $this->students->visibleTo($user);

        $needle = $search === null ? null : mb_strtolower(trim($search));

        return $students
            ->filter(fn (Student $student): bool => $this->matchesClass($student, $className))
            ->filter(fn (Student $student): bool => $this->matchesSearch($student, $needle))
            ->values();
    }

    public function find(int $id, User $user): Student
    {
        $student = $this->students->findVisible($id, $user);

        if ($student === null) {
            throw ValidationException::withMessages([
                'id' => 'We could not find that student in your institution. They may have been removed, or they may be registered at another school.',
            ]);
        }

        return $student;
    }

    public function create(User $user, array $data): Student
    {
        $this->assertRegNoIsFree($user, $data['reg_no']);

        $attributes = [
            'reg_no' => trim((string) $data['reg_no']),
            'first_name' => trim((string) $data['first_name']),
            'last_name' => trim((string) $data['last_name']),
            'class_name' => $data['class_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            // STU-05. A candidate is active unless something says otherwise, so
            // a roster import that omits the column still registers them to sit.
            'status' => $data['status'] ?? Student::STATUS_ACTIVE,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ];

        $model = new Student;
        $model->assignTenant($user, $attributes);

        return $this->students->create($attributes);
    }

    public function update(User $user, Student $student, array $data): Student
    {
        if (array_key_exists('reg_no', $data)) {
            $this->assertRegNoIsFree($user, $data['reg_no'], $student->id);
        }

        $attributes = array_filter(
            [
                'reg_no' => array_key_exists('reg_no', $data) ? trim((string) $data['reg_no']) : null,
                'first_name' => array_key_exists('first_name', $data) ? trim((string) $data['first_name']) : null,
                'last_name' => array_key_exists('last_name', $data) ? trim((string) $data['last_name']) : null,
                'class_name' => array_key_exists('class_name', $data) ? $data['class_name'] : null,
                'phone' => array_key_exists('phone', $data) ? $data['phone'] : null,
                'email' => array_key_exists('email', $data) ? $data['email'] : null,
                'status' => $data['status'] ?? null,
                'updated_by' => $user->id,
            ],
            fn (mixed $value, string $key): bool => $key === 'updated_by' || $value !== null,
            ARRAY_FILTER_USE_BOTH
        );

        return $this->students->update($student, $attributes);
    }

    public function enrol(User $user, Exam $exam, int $studentId): void
    {
        // The paper is resolved through the exam service rather than trusted from
        // the route, so a paper from another school is reported as missing
        // instead of being written to.
        $this->exams->find($exam->id, $user);

        $student = $this->students->findVisible($studentId, $user);

        if ($student === null) {
            throw ValidationException::withMessages([
                'student_id' => 'We could not find that student in your institution, so they cannot be entered for this paper. Please register them first, or pick a student from your own roster.',
            ]);
        }

        if ($exam->students()->whereKey($student->id)->exists()) {
            throw ValidationException::withMessages([
                'student_id' => sprintf(
                    '%s is already entered for "%s". Please remove them first if you need to change the entry.',
                    $student->fullName(),
                    $exam->title
                ),
            ]);
        }

        $this->students->enrollInExam($exam, $student->id, $user->id);
    }

    public function unenrol(User $user, Exam $exam, int $studentId): void
    {
        $this->exams->find($exam->id, $user);

        $student = $this->students->findVisible($studentId, $user);

        if ($student === null || ! $exam->students()->whereKey($studentId)->exists()) {
            throw ValidationException::withMessages([
                'student_id' => sprintf(
                    'That student is not entered for "%s", so there is nothing to remove.',
                    $exam->title
                ),
            ]);
        }

        $this->students->unenroll($exam, $studentId);
    }

    private function matchesClass(Student $student, ?string $className): bool
    {
        if ($className === null || trim($className) === '') {
            return true;
        }

        return mb_strtolower((string) $student->class_name) === mb_strtolower(trim($className));
    }

    /**
     * STU-04 wants a search across registration number and name. Case is folded
     * because a teacher typing "otago" is looking for "Otago", and a registration
     * number is matched as a substring so a partial number is enough to find a
     * candidate on a long roster.
     */
    private function matchesSearch(Student $student, ?string $needle): bool
    {
        if ($needle === null || $needle === '') {
            return true;
        }

        return str_contains(mb_strtolower($student->reg_no), $needle)
            || str_contains(mb_strtolower((string) $student->first_name), $needle)
            || str_contains(mb_strtolower((string) $student->last_name), $needle);
    }

    private function assertRegNoIsFree(User $user, string $regNo, ?int $exceptId = null): void
    {
        $regNo = trim($regNo);

        if ($regNo === '') {
            throw ValidationException::withMessages([
                'reg_no' => 'Please give the student a registration number. It is how their scripts are matched back to them.',
            ]);
        }

        if (! $this->students->regNoExistsFor($user, $regNo, $exceptId)) {
            return;
        }

        throw ValidationException::withMessages([
            'reg_no' => sprintf(
                'The registration number "%s" is already used by another student in your institution. Registration numbers only have to be unique within your school, but not within a school either. Please check the number and try again.',
                $regNo
            ),
        ]);
    }
}