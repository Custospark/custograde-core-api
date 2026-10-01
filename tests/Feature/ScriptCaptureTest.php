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
 Script capture and tenant isolation (CAP-01, CAP-07, STU-07).
 */
class ScriptCaptureTest extends ScriptMarkingTestCase
{
    // --- CAP-01 capture ------------------------------------------------------

    public function test_a_scan_is_stored_and_queued_for_reading(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);

        Bus::assertNothingDispatched();
        $scriptId = $this->uploadScript($exam, $studentId);

        $script = Script::findOrFail($scriptId);

        $this->assertSame(Script::STATUS_UPLOADED, $script->status);
        $this->assertNotNull($script->original_hash);
        Storage::disk('local')->assertExists($script->original_path);

        // Reading a page takes 13 seconds, so it must be queued, not inlined.
        Bus::assertDispatched(ProcessScriptJob::class);
    }

    public function test_a_script_without_a_candidate_is_kept_not_refused(): void
    {
        $exam = $this->makeExam();

        // IDN-05: refusing an unidentified paper would lose it entirely.
        $response = $this->post("/api/v1/exams/{$exam->id}/scripts", [
            'file' => UploadedFile::fake()->image('unknown.png', 1000, 1400),
        ], array_merge($this->headers, ['Accept' => 'application/json']));

        $response->assertCreated();
        $this->assertNotEmpty($response->json('warnings'));
        $this->assertTrue($response->json('script.needs_identification'));
    }

    public function test_the_storage_path_is_never_returned_to_a_client(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);
        $scriptId = $this->uploadScript($exam, $studentId);

        $body = $this->getJson("/api/v1/scripts/{$scriptId}", $this->headers)
            ->assertOk()
            ->json();

        $encoded = json_encode($body);

        $this->assertStringNotContainsString('scripts/', $encoded);
        $this->assertStringNotContainsString('original_path', $encoded);

        // The scan is fetched through a short-lived signed URL instead.
        $this->getJson("/api/v1/scripts/{$scriptId}/image", $this->headers)
            ->assertOk()
            ->assertJsonStructure(['url', 'expires_in']);
    }

    public function test_a_candidate_from_another_school_cannot_be_attached(): void
    {
        $exam = $this->makeExam();

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

        $other = $this->postJson('/api/v1/students', [
            'reg_no' => 'X-1',
            'first_name' => 'Foreign',
            'last_name' => 'Student',
        ], ['Authorization' => 'Bearer ' . $this->tokenFor('admin@hillcrest.test')])->json('id');

        // The request is made as River High again, so the cached guard is
        // dropped first. Without this the request would be served as the
        // Hillcrest admin and the exam would not be found at all, which would
        // make this test pass for the wrong reason.
        $this->post("/api/v1/exams/{$exam->id}/scripts", [
            'student_id' => $other,
            'file' => UploadedFile::fake()->image('x.png'),
        ], array_merge($this->freshGuard($this->headers), ['Accept' => 'application/json']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['student_id']);
    }
}
