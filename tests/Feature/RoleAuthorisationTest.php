<?php

namespace Tests\Feature;

use App\Models\ScriptAnswer;
use App\Models\User;
use App\Support\Capability;
use Illuminate\Support\Facades\Auth;

/**
 * Separation of duties (SEC-07).
 *
 * Before this existed, every controller answered one question about a request:
 * is this row visible to the caller's institution. None of them asked whether
 * the caller was allowed to do the thing. So every user in an institution could
 * release that institution's results, and an `auditor` could decide marks.
 *
 * These tests are the evidence for the change, so they are written as behaviour
 * rather than as a matrix inspection: each one attempts the action over HTTP and
 * requires the refusal.
 */
class RoleAuthorisationTest extends ScriptMarkingTestCase
{
    /**
     * Give the calling user a role without going through an admin flow, which
     * does not exist yet. The point under test is the middleware, not the
     * mechanism for changing a role.
     */
    private function asRole(string $role): array
    {
        $user = User::where('email', 'sarah@riverhigh.test')->firstOrFail();
        $user->update(['role' => $role]);

        return $this->freshGuard(['Authorization' => 'Bearer ' . $this->tokenFor('sarah@riverhigh.test')]);
    }

    // --- the headline gap: releasing results -------------------------------

    public function test_a_teacher_cannot_release_results(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);
        $scriptId = $this->uploadScript($exam, $studentId);
        $this->processScript($scriptId);

        // Setup is done as an examination officer, because the registering
        // account is an institution_admin and deliberately cannot mark. An
        // administrator who marks their own class has nobody checking it.
        $officer = $this->asRole(User::ROLE_EXAMINATION_OFFICER);

        foreach (ScriptAnswer::where('script_id', $scriptId)->get() as $answer) {
            $this->postJson("/api/v1/scripts/{$scriptId}/answers/{$answer->id}/mark", [
                'value' => 4,
            ], $officer)->assertOk();
        }

        $this->postJson("/api/v1/scripts/{$scriptId}/lock", [], $officer)->assertOk();

        // Compilation needs a grading scheme, which is the honest order of
        // operations rather than a test convenience.
        $this->makeGradingScheme();
        $exam->update(['grading_scheme_id' => $this->gradingSchemeId()]);

        $this->postJson("/api/v1/exams/{$exam->id}/results/compile", [], $officer)->assertOk();
        $this->postJson("/api/v1/exams/{$exam->id}/results/release", [], $officer)->assertOk();

        $teacher = $this->asRole(User::ROLE_TEACHER);

        $this->postJson("/api/v1/exams/{$exam->id}/results/withhold", [
            'reason' => 'I would rather not show these',
        ], $teacher)
            ->assertForbidden()
            ->assertJsonPath('code', 'insufficient_capability');
    }

    public function test_a_teacher_can_mark_but_not_amend_an_approved_mark(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);
        $scriptId = $this->uploadScript($exam, $studentId);
        $this->processScript($scriptId);

        $teacher = $this->asRole(User::ROLE_TEACHER);
        $answer = ScriptAnswer::where('script_id', $scriptId)->orderBy('question_number')->first();

        // Making a mark is the ordinary marking path and must keep working.
        $this->postJson("/api/v1/scripts/{$scriptId}/answers/{$answer->id}/mark", [
            'value' => 3,
        ], $teacher)->assertOk();

        foreach (ScriptAnswer::where('script_id', $scriptId)->get() as $each) {
            $this->postJson("/api/v1/scripts/{$scriptId}/answers/{$each->id}/mark", [
                'value' => 3,
            ], $teacher)->assertOk();
        }
        $this->postJson("/api/v1/scripts/{$scriptId}/lock", [], $teacher)->assertOk();

        // Reopening it is a different power, and is refused.
        $this->postJson("/api/v1/scripts/{$scriptId}/unlock", [
            'reason' => 'I want to change my mind',
        ], $teacher)->assertForbidden();
    }

    // --- read only roles ----------------------------------------------------

    public function test_an_auditor_can_read_but_writes_nothing(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);
        $scriptId = $this->uploadScript($exam, $studentId);
        $this->processScript($scriptId);

        $auditor = $this->asRole(User::ROLE_AUDITOR);
        $answer = ScriptAnswer::where('script_id', $scriptId)->first();

        // Reads are exactly what an audit role is for.
        $this->getJson("/api/v1/scripts/{$scriptId}", $auditor)->assertOk();
        $this->getJson("/api/v1/exams/{$exam->id}/results", $auditor)->assertOk();

        // Every write is refused.
        $this->postJson("/api/v1/scripts/{$scriptId}/answers/{$answer->id}/mark", [
            'value' => 4,
        ], $auditor)->assertForbidden();
        $this->postJson("/api/v1/scripts/{$scriptId}/lock", [], $auditor)->assertForbidden();
        $this->postJson("/api/v1/exams/{$exam->id}/results/compile", [], $auditor)->assertForbidden();
        $this->postJson("/api/v1/exams/{$exam->id}/results/release", [], $auditor)->assertForbidden();
    }

    public function test_a_scanning_operator_cannot_read_results(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);
        $scriptId = $this->uploadScript($exam, $studentId);
        $this->processScript($scriptId);

        $scanner = $this->asRole(User::ROLE_SCANNING_OPERATOR);

        // Scanning is the job.
        $this->getJson("/api/v1/scripts/{$scriptId}", $scanner)->assertOk();

        // Reading candidates' marks on the way past is not.
        $this->getJson("/api/v1/exams/{$exam->id}/results", $scanner)->assertForbidden();
        $this->getJson("/api/v1/exams/{$exam->id}/results/statistics", $scanner)->assertForbidden();
    }

    public function test_a_moderator_reviews_but_does_not_mark(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);
        $scriptId = $this->uploadScript($exam, $studentId);
        $this->processScript($scriptId);

        $moderator = $this->asRole(User::ROLE_MODERATOR);
        $answer = ScriptAnswer::where('script_id', $scriptId)->first();

        $this->getJson("/api/v1/scripts/{$scriptId}", $moderator)->assertOk();

        // A moderator who could simply mark would not be moderating, and REV-09
        // requires their decision to be distinguishable from a marker's.
        $this->postJson("/api/v1/scripts/{$scriptId}/answers/{$answer->id}/mark", [
            'value' => 4,
        ], $moderator)->assertForbidden();
    }

    // --- failing closed -----------------------------------------------------

    public function test_an_unrecognised_role_is_granted_nothing(): void
    {
        $exam = $this->makeExam();
        $stranger = $this->asRole('wizard');

        $this->assertSame([], Capability::permissionsFor('wizard'));

        // Reads are refused too, not just writes. A typo in a role column must
        // fail closed rather than behaving like a teacher.
        $this->getJson("/api/v1/exams/{$exam->id}/scripts", $stranger)->assertForbidden();
    }

    public function test_a_student_holds_no_capabilities(): void
    {
        $this->assertSame([], Capability::permissionsFor(User::ROLE_STUDENT));
    }

    // --- tenant scope is still enforced independently ------------------------

    public function test_a_capable_role_still_cannot_reach_another_institution(): void
    {
        // Same officer, but a different institution. Capability is not tenancy:
        // being an examination officer grants nothing across institutions.
        $this->asRole(User::ROLE_EXAMINATION_OFFICER);
        $exam = $this->makeExam();

        // The registering request above left a session on the guard. Forget it,
        // otherwise the second school's token resolves to the first school's
        // user and the test proves nothing.
        Auth::forgetGuards();

        $other = $this->postJson('/api/v1/auth/register', [
            'account_type' => User::ACCOUNT_TYPE_INSTITUTIONAL,
            'first_name' => 'Other',
            'last_name' => 'School',
            'institution_name' => 'Hillcrest Academy',
            'institution_type' => 'Secondary School',
            'email' => 'other@hillcrest.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'privacy_consent' => true,
        ])->assertCreated();

        $this->getJson("/api/v1/exams/{$exam->id}", [
            'Authorization' => 'Bearer ' . $other->json('token'),
        ])->assertNotFound();
    }

    // --- the matrix itself --------------------------------------------------

    public function test_every_declared_role_is_part_of_the_user_catalogue(): void
    {
        foreach (Capability::declaredRoles() as $role) {
            $this->assertContains(
                $role,
                User::ROLES,
                "The capability matrix names a role the User model does not declare: {$role}",
            );
        }
    }

    public function test_the_matrix_grants_nothing_undeclared(): void
    {
        foreach (Capability::declaredRoles() as $role) {
            foreach (Capability::permissionsFor($role) as $capability) {
                $this->assertContains(
                    $capability,
                    Capability::ALL,
                    "Role {$role} is granted an undeclared capability: {$capability}",
                );
            }
        }
    }

    public function test_system_admin_holds_every_capability(): void
    {
        foreach (Capability::ALL as $capability) {
            $this->assertTrue(
                Capability::allows(User::ROLE_SYSTEM_ADMIN, $capability),
                "system_admin must be able to recover a tenant, but cannot {$capability}",
            );
        }
    }
}