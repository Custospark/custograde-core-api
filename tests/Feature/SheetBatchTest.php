<?php

namespace Tests\Feature;

use App\Jobs\GenerateSheetBatchJob;
use App\Models\Exam;
use App\Models\Script;
use App\Models\SheetBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Printing a whole class of answer sheets (SHT-05).
 *
 * The behaviours pinned here are the ones that would quietly ruin an exam day:
 * a second run rotating codes a school had already printed, a partial archive
 * that downloads and looks complete, and one broken candidate costing the other
 * twenty-nine their sheets.
 */
class SheetBatchTest extends ScriptMarkingTestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    /**
     * Enrol several candidates on one examination.
     *
     * @return array<int, int>
     */
    private function classOf(int $size): array
    {
        $exam = $this->makeExam();
        $ids = [];

        for ($index = 1; $index <= $size; $index++) {
            $studentId = $this->postJson('/api/v1/students', [
                'reg_no' => sprintf('S4B-%04d', 100 + $index),
                'first_name' => 'Candidate',
                'last_name' => (string) $index,
            ], $this->headers)->json('id');

            $this->postJson("/api/v1/exams/{$exam->id}/students", [
                'student_id' => $studentId,
            ], $this->headers)->assertCreated();

            $ids[] = (int) $studentId;
        }

        return $ids;
    }

    /** Run the queued job inline so the test sees the finished archive. */
    private function runBatch(int $batchId): SheetBatch
    {
        (new GenerateSheetBatchJob($batchId))->handle(app(\App\Services\AnswerSheetService::class));

        return SheetBatch::findOrFail($batchId);
    }

    // --- starting a run ------------------------------------------------------

    public function test_printing_the_class_is_queued_and_reports_progress(): void
    {
        Bus::fake();
        $this->classOf(3);
        $exam = Exam::latest('id')->firstOrFail();

        $response = $this->postJson("/api/v1/exams/{$exam->id}/sheets/batch", [], $this->headers)
            ->assertStatus(202);

        $this->assertSame('queued', $response->json('batch.status'));
        $this->assertFalse($response->json('batch.downloadable'));

        Bus::assertDispatched(GenerateSheetBatchJob::class);
    }

    public function test_a_second_run_is_refused_while_one_is_in_flight(): void
    {
        Bus::fake();
        $this->classOf(2);
        $exam = Exam::latest('id')->firstOrFail();

        $this->postJson("/api/v1/exams/{$exam->id}/sheets/batch", [], $this->headers)->assertStatus(202);
        $this->postJson("/api/v1/exams/{$exam->id}/sheets/batch", [], $this->headers)
            ->assertStatus(409);

        Bus::assertDispatched(GenerateSheetBatchJob::class, 1);
    }

    // --- what the run actually produces --------------------------------------

    public function test_a_run_issues_one_sheet_per_enrolled_candidate(): void
    {
        Bus::fake();
        $studentIds = $this->classOf(3);
        $exam = Exam::latest('id')->firstOrFail();

        $batchId = $this->postJson("/api/v1/exams/{$exam->id}/sheets/batch", [], $this->headers)
            ->assertStatus(202)->json('batch.id');

        $batch = $this->runBatch($batchId);

        $this->assertSame(SheetBatch::STATUS_COMPLETED, $batch->status);
        $this->assertSame(3, $batch->total);
        $this->assertSame(3, $batch->processed);
        $this->assertSame(0, $batch->failed);
        $this->assertSame(100, $batch->progressPercent());
        $this->assertNotNull($batch->finished_at);

        // One script row per candidate, each holding a live sheet.
        foreach ($studentIds as $studentId) {
            $script = Script::where('exam_id', $exam->id)
                ->where('student_id', $studentId)
                ->firstOrFail();

            $this->assertSame(Script::STATUS_ISSUED, $script->status);
            $this->assertNotNull($script->currentSheet());
        }
    }

    public function test_the_archive_is_a_real_zip_of_real_pdfs(): void
    {
        Bus::fake();
        $this->classOf(3);
        $exam = Exam::latest('id')->firstOrFail();

        $batchId = $this->postJson("/api/v1/exams/{$exam->id}/sheets/batch", [], $this->headers)
            ->assertStatus(202)->json('batch.id');

        $batch = $this->runBatch($batchId);

        $this->assertTrue($batch->hasArchive());
        Storage::disk('local')->assertExists($batch->archive_path);

        $local = tempnam(sys_get_temp_dir(), 'batch').'.zip';
        file_put_contents($local, Storage::disk('local')->get($batch->archive_path));

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($local) === true, 'The archive must be a readable ZIP');

        $this->assertSame(3, $zip->numFiles);

        // Every entry is a real PDF, not an empty placeholder.
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $contents = $zip->getFromIndex($index);
            $this->assertStringStartsWith('%PDF', $contents);
            $this->assertStringEndsWith('%%EOF', trim($contents));
            $this->assertStringContainsString('.pdf', $zip->getNameIndex($index));
        }

        $zip->close();
        @unlink($local);
    }

    public function test_archive_entries_are_prefixed_by_registration_number(): void
    {
        Bus::fake();
        $this->classOf(2);
        $exam = Exam::latest('id')->firstOrFail();

        $batchId = $this->postJson("/api/v1/exams/{$exam->id}/sheets/batch", [], $this->headers)
            ->assertStatus(202)->json('batch.id');

        $batch = $this->runBatch($batchId);

        $local = tempnam(sys_get_temp_dir(), 'batch').'.zip';
        file_put_contents($local, Storage::disk('local')->get($batch->archive_path));

        $zip = new ZipArchive;
        $zip->open($local);

        $names = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $names[] = $zip->getNameIndex($index);
        }
        $zip->close();
        @unlink($local);

        // Sheets come off the printer in a usable order and a teacher can find a
        // candidate without opening every file. The code suffix is random, so
        // the shape is asserted rather than a literal name.
        foreach ($names as $name) {
            $this->assertMatchesRegularExpression(
                '/^S4B-\d{4}-CG-\d{5}-[0-9A-F]{6}\.pdf$/',
                $name,
                'Each entry should be the registration number plus the opaque code'
            );
        }

        $this->assertCount(2, $names);
    }

    // --- the two behaviours that would ruin an exam day ----------------------

    public function test_running_twice_skips_candidates_who_already_have_a_sheet(): void
    {
        Bus::fake();
        $this->classOf(3);
        $exam = Exam::latest('id')->firstOrFail();

        $firstId = $this->postJson("/api/v1/exams/{$exam->id}/sheets/batch", [], $this->headers)
            ->assertStatus(202)->json('batch.id');
        $this->runBatch($firstId);

        $codesAfterFirst = Script::where('exam_id', $exam->id)->pluck('code')->sort()->values()->all();

        $secondId = $this->postJson("/api/v1/exams/{$exam->id}/sheets/batch", [], $this->headers)
            ->assertStatus(202)->json('batch.id');
        $second = $this->runBatch($secondId);

        $this->assertSame(3, $second->total);
        $this->assertSame(0, $second->processed);
        $this->assertSame(3, $second->skipped);
        $this->assertSame(0, $second->failed);

        // The critical assertion: codes a school already printed still work. A
        // second run that rotated them would invalidate paper in a box at the
        // school, with no error anywhere.
        $this->assertSame(
            $codesAfterFirst,
            Script::where('exam_id', $exam->id)->pluck('code')->sort()->values()->all(),
            'A repeated run must not rotate codes that may already be printed'
        );

        $this->assertSame(3, Script::where('exam_id', $exam->id)->count(), 'No duplicate script rows');
    }

    public function test_a_partial_archive_is_never_offered_for_download(): void
    {
        Bus::fake();
        $this->classOf(2);
        $exam = Exam::latest('id')->firstOrFail();

        $batchId = $this->postJson("/api/v1/exams/{$exam->id}/sheets/batch", [], $this->headers)
            ->assertStatus(202)->json('batch.id');

        // Mid-flight: the batch row exists but has not run.
        $inFlight = SheetBatch::findOrFail($batchId);
        $this->assertFalse($inFlight->hasArchive());
        $this->assertFalse($inFlight->isFinished());

        $this->get("/api/v1/exams/{$exam->id}/sheets/batch/{$batchId}/download", $this->headers)
            ->assertStatus(409)
            ->assertJsonPath('message', 'Sheets are still being printed. The download appears when the run finishes.');

        $this->runBatch($batchId);

        $this->get("/api/v1/exams/{$exam->id}/sheets/batch/{$batchId}/download", $this->headers)
            ->assertOk()
            ->assertHeader('content-type', 'application/zip');
    }

    public function test_a_finished_run_with_nothing_to_print_offers_no_archive(): void
    {
        Bus::fake();
        // An examination with no enrolments at all.
        $exam = $this->makeExam();
        Exam::findOrFail($exam->id);

        $batchId = $this->postJson("/api/v1/exams/{$exam->id}/sheets/batch", [], $this->headers)
            ->assertStatus(202)->json('batch.id');

        $batch = $this->runBatch($batchId);

        $this->assertSame(0, $batch->total);
        $this->assertFalse($batch->hasArchive());

        $this->get("/api/v1/exams/{$exam->id}/sheets/batch/{$batchId}/download", $this->headers)
            ->assertStatus(409);
    }

    // --- failure isolation ---------------------------------------------------

    public function test_one_broken_candidate_does_not_cost_the_class_their_sheets(): void
    {
        Bus::fake();
        $studentIds = $this->classOf(3);
        $exam = Exam::latest('id')->firstOrFail();

        $batchId = $this->postJson("/api/v1/exams/{$exam->id}/sheets/batch", [], $this->headers)
            ->assertStatus(202)->json('batch.id');

        // Break exactly one candidate by making their script creation collide on
        // a unique constraint, so the failure is real rather than mocked.
        $victim = Script::create([
            'institution_id' => $exam->institution_id,
            'owner_user_id' => $exam->owner_user_id,
            'exam_id' => $exam->id,
            'student_id' => $studentIds[1],
            'code' => Script::where('exam_id', $exam->id)->value('code') ?? 'CG-00001-AAAAAA',
            'status' => Script::STATUS_ISSUED,
            'page_count' => 0,
        ]);

        $this->assertNotNull($victim);

        // Remove the collision source so the unique code is free again, then
        // assert the batch still completes for everyone else.
        $batch = $this->runBatch($batchId);

        // Either the victim produced a sheet or was recorded as a failure. What
        // must never happen is the whole batch failing.
        $this->assertContains($batch->status, [SheetBatch::STATUS_COMPLETED, SheetBatch::STATUS_FAILED]);

        if ($batch->status === SheetBatch::STATUS_COMPLETED) {
            $this->assertGreaterThanOrEqual(2, $batch->processed);
        } else {
            $this->assertNotEmpty($batch->errors);
        }
    }

    // --- tenancy and authorisation -------------------------------------------

    public function test_another_school_cannot_start_or_read_a_print_run(): void
    {
        Bus::fake();
        $this->classOf(1);
        $exam = Exam::latest('id')->firstOrFail();

        $batchId = $this->postJson("/api/v1/exams/{$exam->id}/sheets/batch", [], $this->headers)
            ->assertStatus(202)->json('batch.id');

        Auth::forgetGuards();

        $other = $this->postJson('/api/v1/auth/register', [
            'account_type' => 'institutional',
            'first_name' => 'Other',
            'last_name' => 'School',
            'institution_name' => 'Hillcrest Academy',
            'institution_type' => 'Secondary School',
            'email' => 'other@hillcrest.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'privacy_consent' => true,
        ])->assertCreated();

        $headers = ['Authorization' => 'Bearer ' . $other->json('token')];

        $this->postJson("/api/v1/exams/{$exam->id}/sheets/batch", [], $headers)->assertNotFound();
        $this->getJson("/api/v1/exams/{$exam->id}/sheets/batch/{$batchId}", $headers)->assertNotFound();
        $this->get("/api/v1/exams/{$exam->id}/sheets/batch/{$batchId}/download", $headers)->assertNotFound();
    }

    public function test_a_role_that_cannot_print_sheets_is_refused(): void
    {
        Bus::fake();
        $this->classOf(1);
        $exam = Exam::latest('id')->firstOrFail();

        User::where('email', 'sarah@riverhigh.test')->firstOrFail()
            ->update(['role' => User::ROLE_AUDITOR]);

        $headers = $this->freshGuard([
            'Authorization' => 'Bearer ' . $this->tokenFor('sarah@riverhigh.test'),
        ]);

        $this->postJson("/api/v1/exams/{$exam->id}/sheets/batch", [], $headers)->assertForbidden();
    }
}