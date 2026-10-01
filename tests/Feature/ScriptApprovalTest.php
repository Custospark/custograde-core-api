<?php

namespace Tests\Feature;

use App\Jobs\ProcessScriptJob;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\Script;
use App\Models\ScriptAnswer;
use App\Models\User;
use App\Services\Contracts\AiServiceInterface;
use App\Services\Contracts\ScriptPipelineServiceInterface;
use App\Services\ScriptPipelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;


/**
 Approving, locking and reopening a script (REV-01, REV-07, REV-08, MRK-02).
 */
class ScriptApprovalTest extends ScriptMarkingTestCase
{
    // --- REV-08 and MRK-02: the lock gate ------------------------------------

    public function test_a_script_cannot_be_approved_while_a_question_is_unmarked(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);
        $scriptId = $this->uploadScript($exam, $studentId);
        $this->processScript($scriptId);

        $answers = ScriptAnswer::where('script_id', $scriptId)->orderBy('question_number')->get();

        // Mark two of the three. The graph is still undecided.
        $this->postJson("/api/v1/scripts/{$scriptId}/answers/{$answers[0]->id}/mark", [
            'value' => 4,
        ], $this->headers)->assertOk();
        $this->postJson("/api/v1/scripts/{$scriptId}/answers/{$answers[1]->id}/mark", [
            'value' => 4,
        ], $this->headers)->assertOk();

        $response = $this->postJson("/api/v1/scripts/{$scriptId}/lock", [], $this->headers)
            ->assertStatus(422);

        // The message names the outstanding question rather than failing vaguely.
        $this->assertStringContainsString('3', $response->json('message'));
    }

    public function test_a_graph_is_marked_by_hand_and_then_the_script_approves(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);
        $scriptId = $this->uploadScript($exam, $studentId);
        $this->processScript($scriptId);

        $answers = ScriptAnswer::where('script_id', $scriptId)->orderBy('question_number')->get();

        // Question 3 is a graph, so the model refused it. A teacher grades it.
        $this->assertTrue($answers[2]->needsTeacher());
        $this->assertNull($answers[2]->suggested_mark);

        foreach ([[0, 4], [1, 4], [2, 3]] as [$index, $value]) {
            $this->postJson("/api/v1/scripts/{$scriptId}/answers/{$answers[$index]->id}/mark", [
                'value' => $value,
            ], $this->headers)->assertOk();
        }

        $this->postJson("/api/v1/scripts/{$scriptId}/lock", [], $this->headers)
            ->assertOk()
            ->assertJsonPath('script.status', Script::STATUS_LOCKED);

        $script = Script::findOrFail($scriptId);
        $this->assertNotNull($script->locked_by);
        $this->assertNotNull($script->locked_at);
        $this->assertEquals(11.0, (float) $script->total_mark);
    }

    public function test_a_script_with_a_missing_page_cannot_be_approved(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);
        $scriptId = $this->uploadScript($exam, $studentId);
        $this->processScript($scriptId);

        Script::findOrFail($scriptId)->update(['page_count' => 1, 'expected_page_count' => 3]);

        $answers = ScriptAnswer::where('script_id', $scriptId)->get();
        foreach ($answers as $answer) {
            $this->postJson("/api/v1/scripts/{$scriptId}/answers/{$answer->id}/mark", [
                'value' => 1,
            ], $this->headers)->assertOk();
        }

        $this->postJson("/api/v1/scripts/{$scriptId}/lock", [], $this->headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['script']);
    }

    public function test_a_flagged_script_cannot_be_approved(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);
        $scriptId = $this->uploadScript($exam, $studentId);
        $this->processScript($scriptId);

        foreach (ScriptAnswer::where('script_id', $scriptId)->get() as $answer) {
            $this->postJson("/api/v1/scripts/{$scriptId}/answers/{$answer->id}/mark", [
                'value' => 1,
            ], $this->headers)->assertOk();
        }

        $this->postJson("/api/v1/scripts/{$scriptId}/flag", [
            'note' => 'Two scripts have identical wording.',
        ], $this->headers)->assertOk();

        $this->postJson("/api/v1/scripts/{$scriptId}/lock", [], $this->headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['script']);
    }

    public function test_an_approved_script_rejects_further_mark_changes(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);
        $scriptId = $this->uploadScript($exam, $studentId);
        $this->processScript($scriptId);

        $answers = ScriptAnswer::where('script_id', $scriptId)->get();
        foreach ($answers as $answer) {
            $this->postJson("/api/v1/scripts/{$scriptId}/answers/{$answer->id}/mark", [
                'value' => 1,
            ], $this->headers)->assertOk();
        }
        $this->postJson("/api/v1/scripts/{$scriptId}/lock", [], $this->headers)->assertOk();

        $this->postJson("/api/v1/scripts/{$scriptId}/answers/{$answers[0]->id}/mark", [
            'value' => 2,
            'reason' => 'Changed my mind.',
        ], $this->headers)->assertStatus(422)
            ->assertJsonValidationErrors(['script']);
    }

    public function test_reopening_an_approved_script_requires_a_reason(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);
        $scriptId = $this->uploadScript($exam, $studentId);
        $this->processScript($scriptId);

        foreach (ScriptAnswer::where('script_id', $scriptId)->get() as $answer) {
            $this->postJson("/api/v1/scripts/{$scriptId}/answers/{$answer->id}/mark", [
                'value' => 1,
            ], $this->headers)->assertOk();
        }
        $this->postJson("/api/v1/scripts/{$scriptId}/lock", [], $this->headers)->assertOk();

        $this->postJson("/api/v1/scripts/{$scriptId}/unlock", [], $this->headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);

        $this->postJson("/api/v1/scripts/{$scriptId}/unlock", [
            'reason' => 'A parent queried this result.',
        ], $this->headers)->assertOk()
            ->assertJsonPath('script.status', Script::STATUS_IN_REVIEW);
    }

    // --- STU-07: blind marking ----------------------------------------------

    public function test_a_blind_exam_hides_the_candidate_from_the_api(): void
    {
        $exam = $this->makeExam(['blind_marking' => true]);
        $studentId = $this->makeStudent($exam);
        $scriptId = $this->uploadScript($exam, $studentId);

        $body = $this->getJson("/api/v1/scripts/{$scriptId}", $this->headers)->assertOk()->json();

        // The identity must not be sent at all, not merely hidden in the client.
        $this->assertArrayNotHasKey('student', $body);
        $this->assertArrayNotHasKey('student_id', $body);
        $this->assertSame($scriptId, $body['id']);
    }

    // --- isolation -----------------------------------------------------------

    public function test_another_school_cannot_read_a_script(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);
        $scriptId = $this->uploadScript($exam, $studentId);

        $this->postJson('/api/v1/auth/register', [
            'account_type' => User::ACCOUNT_TYPE_INSTITUTIONAL,
            'first_name' => 'Other',
            'last_name' => 'Admin',
            'institution_name' => 'Hillcrest School',
            'institution_type' => 'Secondary School',
            'email' => 'admin@hillcrest.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'privacy_consent' => true,
        ])->assertCreated();
        Auth::forgetGuards();

        $this->getJson("/api/v1/scripts/{$scriptId}", [
            'Authorization' => 'Bearer ' . $this->tokenFor('admin@hillcrest.test'),
        ])->assertNotFound();
    }

    public function test_guests_cannot_reach_scripts_or_marks(): void
    {
        $this->getJson('/api/v1/exams/1/scripts')->assertStatus(401);
        $this->getJson('/api/v1/scripts/1')->assertStatus(401);
        $this->postJson('/api/v1/scripts/1/lock', [])->assertStatus(401);
        $this->getJson('/api/v1/exams/1/results')->assertStatus(401);
    }
}
