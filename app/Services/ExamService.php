<?php

namespace App\Services;

use App\Models\CourseUnit;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\Term;
use App\Models\User;
use App\Repositories\Contracts\CourseUnitRepositoryInterface;
use App\Repositories\Contracts\ExamRepositoryInterface;
use App\Repositories\Contracts\GradingSchemeRepositoryInterface;
use App\Repositories\Contracts\TermRepositoryInterface;
use App\Services\Contracts\ExamServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Examination papers and their questions (EXM-01, EXM-02, EXM-06).
 *
 * Three decisions live here rather than in a repository. A paper hangs off a
 * course, a term and a grading scheme, and all three are checked for visibility
 * on every write, because a request carrying another school's id would
 * otherwise attach a paper to a structure the caller cannot see. Questions are
 * refused once the paper is closed, and the paper totals are recomputed after
 * every question write, because a total that drifts from its questions is the
 * one thing a marker cannot be allowed to trust.
 */
class ExamService implements ExamServiceInterface
{
    public function __construct(
        private ExamRepositoryInterface $exams,
        private CourseUnitRepositoryInterface $courseUnits,
        private TermRepositoryInterface $terms,
        private GradingSchemeRepositoryInterface $schemes,
    ) {}

    public function list(User $user, ?int $courseUnitId = null): Collection
    {
        if ($courseUnitId === null) {
            return $this->exams->visibleTo($user);
        }

        $this->assertCourseIsVisible($user, $courseUnitId);

        return $this->exams->forCourseUnit($courseUnitId, $user);
    }

    public function find(int $id, User $user): Exam
    {
        $exam = $this->exams->withQuestions($id, $user);

        if ($exam === null) {
            throw ValidationException::withMessages([
                'id' => 'We could not find that examination in your institution. It may have been removed, or it may belong to another school.',
            ]);
        }

        return $exam;
    }

    public function create(User $user, array $data): Exam
    {
        $courseUnit = $this->assertCourseIsVisible($user, (int) $data['course_unit_id']);
        $term = $this->assertTermIsVisible($user, $data['term_id'] ?? null);
        $this->assertSchemeIsVisible($user, $data['grading_scheme_id'] ?? null);

        $attributes = [
            'course_unit_id' => $courseUnit->id,
            'term_id' => $term?->id,
            'grading_scheme_id' => $data['grading_scheme_id'] ?? null,
            'title' => trim((string) $data['title']),
            'type' => $data['type'],
            'exam_date' => $data['exam_date'],
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'blind_marking' => $data['blind_marking'] ?? false,
            // STU-08: results stay hidden until an institution turns this on, so
            // the safe default is false rather than whatever the paper was made
            // inside a context that assumed otherwise.
            'results_visible_to_students' => $data['results_visible_to_students'] ?? false,
            'status' => Exam::STATUS_DRAFT,
            // A new paper has no questions yet. Recomputed as they arrive.
            'total_marks' => 0,
            'question_count' => 0,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ];

        $model = new Exam;
        $model->assignTenant($user, $attributes);

        return $this->exams->create($attributes);
    }

    public function update(User $user, Exam $exam, array $data): Exam
    {
        $this->assertPaperIsOpen($exam);

        if (array_key_exists('course_unit_id', $data) && $data['course_unit_id'] !== null) {
            $this->assertCourseIsVisible($user, (int) $data['course_unit_id']);
        }

        if (array_key_exists('term_id', $data)) {
            $this->assertTermIsVisible($user, $data['term_id']);
        }

        if (array_key_exists('grading_scheme_id', $data)) {
            $this->assertSchemeIsVisible($user, $data['grading_scheme_id']);
        }

        $attributes = array_filter(
            [
                'course_unit_id' => array_key_exists('course_unit_id', $data) ? $data['course_unit_id'] : null,
                'term_id' => array_key_exists('term_id', $data) ? $data['term_id'] : null,
                'grading_scheme_id' => array_key_exists('grading_scheme_id', $data) ? $data['grading_scheme_id'] : null,
                'title' => array_key_exists('title', $data) ? trim((string) $data['title']) : null,
                'type' => $data['type'] ?? null,
                'exam_date' => $data['exam_date'] ?? null,
                'duration_minutes' => array_key_exists('duration_minutes', $data) ? $data['duration_minutes'] : null,
                // false is a legitimate value here, so presence is decided by
                // array_key_exists rather than by truthiness.
                'blind_marking' => array_key_exists('blind_marking', $data) ? (bool) $data['blind_marking'] : null,
                'results_visible_to_students' => array_key_exists('results_visible_to_students', $data)
                    ? (bool) $data['results_visible_to_students']
                    : null,
                'updated_by' => $user->id,
            ],
            fn (mixed $value, string $key): bool => $key === 'updated_by' || $value !== null,
            ARRAY_FILTER_USE_BOTH
        );

        return $this->exams->update($exam, $attributes);
    }

    public function delete(User $user, Exam $exam): void
    {
        $scriptCount = $exam->scripts()->count();

        if ($scriptCount > 0) {
            throw ValidationException::withMessages([
                'id' => sprintf(
                    '"%s" already has %d script(s) captured against it, and deleting it would take the record of that work with it. Please archive the paper instead, which keeps the scripts and the marks attached to them.',
                    $exam->title,
                    $scriptCount
                ),
            ]);
        }

        $this->exams->delete($exam);
    }

    public function addQuestion(User $user, Exam $exam, array $data): ExamQuestion
    {
        $this->assertPaperIsOpen($exam);

        $this->assertNumberIsFree($exam, (int) $data['number']);

        // The question is written through the paper's relation, so exam_id is
        // never taken from the request body and a question cannot be attached to
        // a paper the caller was not authorised for.
        $question = $exam->questions()->create($this->questionAttributes($user, $exam, $data));

        $this->recalculateTotals($exam);

        return $question->refresh();
    }

    public function updateQuestion(User $user, Exam $exam, int $questionId, array $data): ExamQuestion
    {
        $this->assertPaperIsOpen($exam);

        $question = $this->findQuestion($exam, $questionId);

        if (array_key_exists('number', $data) && (int) $data['number'] !== (int) $question->number) {
            $this->assertNumberIsFree($exam, (int) $data['number'], $question->id);
        }

        $question->fill(array_filter(
            $this->questionAttributes($user, $exam, $data),
            fn (mixed $value, string $key): bool => $value !== null,
            ARRAY_FILTER_USE_BOTH
        ));

        $question->save();

        // The paper total is what a marker checks a mark against, so it moves
        // whenever a question maximum does, not only when one is removed.
        $this->recalculateTotals($exam);

        return $question->refresh();
    }

    public function deleteQuestion(User $user, Exam $exam, int $questionId): void
    {
        $this->assertPaperIsOpen($exam);

        $question = $this->findQuestion($exam, $questionId);

        $question->delete();

        // Removing a question changes both the count and the total, so the paper
        // is never left quoting marks no question can be worth.
        $this->recalculateTotals($exam);
    }

    public function setStatus(User $user, Exam $exam, string $status): Exam
    {
        if (! in_array($status, Exam::STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => sprintf(
                    'That is not a stage this paper can be at. Please choose one of: %s.',
                    implode(', ', Exam::STATUSES)
                ),
            ]);
        }

        return $this->exams->update($exam, [
            'status' => $status,
            'updated_by' => $user->id,
        ]);
    }

    public function recalculateTotals(Exam $exam): Exam
    {
        return $this->exams->recalculateTotals($exam);
    }

    /**
     * The shape shared by a question create and an update, so a mark ceiling and
     * an audit column cannot drift between the two paths.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function questionAttributes(User $user, Exam $exam, array $data): array
    {
        return array_merge(
            ['exam_id' => $exam->id, 'updated_by' => $user->id],
            array_filter(
                [
                    'number' => array_key_exists('number', $data) ? (int) $data['number'] : null,
                    'prompt' => array_key_exists('prompt', $data) ? $data['prompt'] : null,
                    'kind' => $data['kind'] ?? null,
                    'max_mark' => array_key_exists('max_mark', $data) ? (float) $data['max_mark'] : null,
                    'granularity' => array_key_exists('granularity', $data) ? (float) $data['granularity'] : null,
                    'model_answer' => array_key_exists('model_answer', $data) ? $data['model_answer'] : null,
                    'guide_points' => array_key_exists('guide_points', $data) ? $data['guide_points'] : null,
                    'options' => array_key_exists('options', $data) ? $data['options'] : null,
                    'answer_key' => array_key_exists('answer_key', $data) ? $data['answer_key'] : null,
                    'created_by' => $user->id,
                ],
                fn (mixed $value): bool => $value !== null,
            )
        );
    }

    private function assertCourseIsVisible(User $user, int $courseUnitId): CourseUnit
    {
        $courseUnit = $this->courseUnits->findVisible($courseUnitId, $user);

        if ($courseUnit === null) {
            throw ValidationException::withMessages([
                'course_unit_id' => 'We could not find that course in your institution. Please choose a course from your own list, because a paper cannot be set on another school\'s course.',
            ]);
        }

        return $courseUnit;
    }

    private function assertTermIsVisible(User $user, ?int $termId): ?Term
    {
        if ($termId === null) {
            return null;
        }

        $term = $this->terms->findVisible($termId, $user);

        if ($term === null) {
            throw ValidationException::withMessages([
                'term_id' => 'We could not find that term in your institution. Please choose a term from your own calendar, or leave it empty for a paper that is not tied to one.',
            ]);
        }

        return $term;
    }

    private function assertSchemeIsVisible(User $user, ?int $schemeId): void
    {
        if ($schemeId === null) {
            return;
        }

        $scheme = $this->schemes->findVisible($schemeId, $user);

        if ($scheme === null) {
            throw ValidationException::withMessages([
                'grading_scheme_id' => 'We could not find that grading scheme in your institution. Please choose one of your own schemes, or leave it empty to use the default scheme.',
            ]);
        }
    }

    /**
     * A paper stops taking changes once it is finalised or archived. Marks may
     * already have been settled against its questions by then, so an edit would
     * silently rewrite the ceiling those marks were judged by.
     */
    private function assertPaperIsOpen(Exam $exam): void
    {
        if (! in_array($exam->status, Exam::CLOSED_STATUSES, true)) {
            return;
        }

        throw ValidationException::withMessages([
            'id' => sprintf(
                '"%s" is %s, so its questions can no longer be changed. Please set it back to draft if this was a mistake, or copy it into a new paper instead.',
                $exam->title,
                $exam->status
            ),
        ]);
    }

    /**
     * Numbering is what a candidate reads on the paper and what a marker reads
     * in the mark box, so two questions cannot share one number. Checked here so
     * the refusal names the number rather than surfacing a unique-constraint
     * failure.
     */
    private function assertNumberIsFree(Exam $exam, int $number, ?int $exceptId = null): void
    {
        $query = $exam->questions()->where('number', $number);

        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }

        if (! $query->exists()) {
            return;
        }

        throw ValidationException::withMessages([
            'number' => sprintf(
                'Question %d is already on "%s". Please give this question a number that is not taken, or renumber the others.',
                $number,
                $exam->title
            ),
        ]);
    }

    /**
     * A question is looked up through its paper rather than by id alone, so an id
     * from another paper reports as missing instead of being edited.
     */
    private function findQuestion(Exam $exam, int $questionId): ExamQuestion
    {
        $question = $exam->questions()->whereKey($questionId)->first();

        if ($question === null) {
            throw ValidationException::withMessages([
                'question' => sprintf(
                    'We could not find that question on "%s". It may have been removed, or it may belong to a different paper.',
                    $exam->title
                ),
            ]);
        }

        return $question;
    }
}