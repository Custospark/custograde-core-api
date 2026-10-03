<?php

namespace Tests\Feature;

use App\Jobs\ResolveIdentificationJob;
use App\Models\Script;
use App\Models\ScriptAnswer;
use App\Services\ScanIdentifier;
use App\Services\SheetCodeService;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

/**
 * The deeper identification pass (IDN-08).
 *
 * The behaviour that matters most here is the refusal to guess. A scan whose code
 * points at another candidate's sheet must never be silently reassigned, because
 * that is how one candidate ends up with another candidate's marks.
 */
class ResolveIdentificationTest extends ScriptMarkingTestCase
{
    use \Tests\Concerns\WritesScratchImages;

    /**
     * Draw a code onto an A4 canvas.
     *
     * Mirrored places it top LEFT, which the inline single attempt never looks at.
     * That is the exact case this job exists to rescue.
     */
    private function sheetWithCodeOnScan(string $code, string $scanPath, bool $mirror = false): UploadedFile
    {
        $script = new Script(['code' => $code]);
        $script->id = 1;
        $payload = app(SheetCodeService::class)->encode($script, 1, 1);

        $options = new QROptions;
        $options->outputType = QROutputInterface::GDIMAGE_PNG;
        $options->eccLevel = EccLevel::H;
        (new QRCode($options))->render($payload, $scanPath.'.qr.png');
        $codeImage = imagecreatefrompng($scanPath.'.qr.png');

        $page = imagecreatetruecolor(1654, 2339);
        imagefilledrectangle($page, 0, 0, 1654, 2339, imagecolorallocate($page, 251, 250, 246));

        $x = $mirror ? 200 : 1300;
        imagecopyresampled(
            $page, $codeImage, $x, 200, 0, 0, 120, 120,
            imagesx($codeImage), imagesy($codeImage)
        );
        imagepng($page, $scanPath);

        return $this->wrap($scanPath);
    }

    private function wrap(string $path): UploadedFile
    {
        return new UploadedFile(
            realpath($path) ?: $path,
            basename($path),
            'image/png',
            UPLOAD_ERR_OK,
            true
        );
    }

    private function issueSheet(int $examId, int $studentId): array
    {
        return $this->postJson("/api/v1/exams/{$examId}/sheets", ['student_id' => $studentId], $this->headers)
            ->assertCreated()->json('sheet');
    }

    private function attemptResolution(int $scriptId): void
    {
        (new ResolveIdentificationJob($scriptId))->handle(app(ScanIdentifier::class));
    }

    /**
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    private function uploadHeaders(array $extra = []): array
    {
        return array_merge($extra, ['Accept' => 'application/json']);
    }

    // --- the case this job exists for ---------------------------------------

    public function test_a_code_where_the_quick_pass_does_not_look_is_still_matched(): void
    {
        Bus::fake();
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);
        $sheet = $this->issueSheet($exam->id, $studentId);

        // Mirrored to the top left. The inline attempt only looks top right, so
        // this arrives unidentified.
        $file = $this->sheetWithCodeOnScan($sheet['code'], $this->scratch('resolve_mirror.png'), mirror: true);

        $placeholderId = $this->post("/api/v1/exams/{$exam->id}/scripts", [
            'file' => $file,
        ], $this->uploadHeaders())->assertCreated()->json('script.id');

        $this->assertNotSame(
            $sheet['script_id'],
            $placeholderId,
            'Precondition: the quick inline pass must have missed this layout'
        );
        $this->assertNull(Script::findOrFail($placeholderId)->student_id);

        $this->attemptResolution($placeholderId);

        $target = Script::findOrFail($sheet['script_id']);
        $this->assertSame($studentId, $target->student_id);
        $this->assertNotNull($target->original_path);
        $this->assertNull($target->flag_note);
        $this->assertSame(Script::STATUS_UPLOADED, $target->status);

        $this->assertNull(
            Script::find($placeholderId),
            'An empty placeholder must not linger as a second paper for one candidate'
        );
    }

    // --- refusals ------------------------------------------------------------

    public function test_a_code_whose_sheet_already_holds_a_scan_is_not_reassigned(): void
    {
        Bus::fake();
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);

        $sheet = $this->issueSheet($exam->id, $studentId);

        // The first copy of this candidate's sheet uploads and matches at once.
        $ok = $this->sheetWithCodeOnScan($sheet['code'], $this->scratch('resolve_first.png'));
        $this->post("/api/v1/exams/{$exam->id}/scripts", ['file' => $ok], $this->uploadHeaders())
            ->assertCreated();

        // The same sheet again, in a layout the quick pass misses.
        $again = $this->sheetWithCodeOnScan($sheet['code'], $this->scratch('resolve_again.png'), mirror: true);
        $placeholderId = $this->post("/api/v1/exams/{$exam->id}/scripts", [
            'file' => $again,
        ], $this->uploadHeaders())->assertCreated()->json('script.id');

        $before = Script::findOrFail($placeholderId)->student_id;

        $this->attemptResolution($placeholderId);

        $after = Script::findOrFail($placeholderId);
        $this->assertSame($before, $after->student_id, 'This job may not assign a candidate');
        $this->assertNotEmpty($after->flag_note, 'The paper must be handed to a person');
        $this->assertSame(Script::STATUS_FLAGGED, $after->status);

        // The sheet keeps the scan it already had.
        $this->assertNotNull(Script::findOrFail($sheet['script_id'])->original_path);
    }

    public function test_marks_already_read_against_a_placeholder_stop_it_being_moved(): void
    {
        Bus::fake();
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);
        $sheet = $this->issueSheet($exam->id, $studentId);

        $file = $this->sheetWithCodeOnScan($sheet['code'], $this->scratch('resolve_answers.png'), mirror: true);
        $placeholderId = $this->post("/api/v1/exams/{$exam->id}/scripts", [
            'file' => $file,
        ], $this->uploadHeaders())->assertCreated()->json('script.id');

        // Give it an answer, exactly as the transcription pipeline would.
        ScriptAnswer::create([
            'script_id' => $placeholderId,
            'exam_id' => $exam->id,
            'question_id' => $exam->questions()->first()->id,
            'question_number' => 1,
            'machine_text' => 'something read from this page',
        ]);

        $this->attemptResolution($placeholderId);

        $kept = Script::findOrFail($placeholderId);
        $this->assertTrue($kept->answers()->exists(), 'Marks must never be deleted by this job');
        $this->assertSame(Script::STATUS_FLAGGED, $kept->status);
        $this->assertStringContainsString('already read against it', (string) $kept->flag_note);

        $this->assertNull(
            Script::findOrFail($sheet['script_id'])->original_path,
            'The sheet it came from must be untouched while the conflict is unresolved'
        );
    }

    public function test_an_unreadable_page_is_left_for_a_person_rather_than_deleted(): void
    {
        Bus::fake();
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);

        $page = imagecreatetruecolor(1654, 2339);
        imagefilledrectangle($page, 0, 0, 1654, 2339, imagecolorallocate($page, 255, 255, 255));
        imagepng($page, $this->scratch('resolve_blank.png'));

        $placeholderId = $this->post("/api/v1/exams/{$exam->id}/scripts", [
            'file' => $this->wrap($this->scratch('resolve_blank.png')),
            'student_id' => $studentId,
        ], $this->uploadHeaders())->assertCreated()->json('script.id');

        $this->attemptResolution($placeholderId);

        $kept = Script::findOrFail($placeholderId);
        $this->assertSame($studentId, $kept->student_id);
        $this->assertNotNull($kept->original_path);
    }

    public function test_a_missing_file_is_escalated_rather_than_silently_dropped(): void
    {
        Bus::fake();
        $exam = $this->makeExam();
        $scriptId = $this->uploadScript($exam, null);

        // Remove the bytes behind the row, which is what a lost disk looks like. The
        // path is nested one level deeper than the exam folder, so this walks it.
        $disk = Storage::disk('local');
        foreach ($disk->allFiles('scripts/'.$exam->id) as $file) {
            $disk->delete($file);
        }

        $this->attemptResolution($scriptId);

        $script = Script::findOrFail($scriptId);
        $this->assertSame(Script::STATUS_FLAGGED, $script->status);
        $this->assertStringContainsString('missing', (string) $script->flag_note);
    }

    public function test_the_job_is_queued_rather_than_run_during_the_upload(): void
    {
        Bus::fake();
        $exam = $this->makeExam();

        $page = imagecreatetruecolor(1654, 2339);
        imagefilledrectangle($page, 0, 0, 1654, 2339, imagecolorallocate($page, 255, 255, 255));
        imagepng($page, $this->scratch('resolve_queued.png'));

        $this->post("/api/v1/exams/{$exam->id}/scripts", [
            'file' => $this->wrap($this->scratch('resolve_queued.png')),
        ], $this->uploadHeaders())->assertCreated();

        Bus::assertDispatched(ResolveIdentificationJob::class);
    }
}