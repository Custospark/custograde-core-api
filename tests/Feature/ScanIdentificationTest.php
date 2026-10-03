<?php

namespace Tests\Feature;

use App\Models\Script;
use App\Services\ScanIdentifier;
use App\Services\SheetCodeService;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Automatic identification of a scan from its sheet code (IDN-02).
 *
 * These tests build a real QR, composite it onto a realistic A4 canvas at the
 * size and position the sheet template puts it, and upload that. Nothing is
 * stubbed, because the whole risk here is in the image handling: a mock would
 * happily pass while the detector never actually read anything.
 */
class ScanIdentificationTest extends ScriptMarkingTestCase
{
    /**
     * Headers for a multipart upload.
     *
     * The Accept header matters: without it a validation failure answers with a
     * 302 redirect rather than a 422, which reads as an unexplained failure
     * instead of a diagnosable one.
     *
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    private function uploadHeaders(array $extra = []): array
    {
        return array_merge($extra, ['Accept' => 'application/json']);
    }

    /**
     * Wrap an on-disk image as an upload.
     *
     * The absolute path is not incidental. Symfony's UploadedFile extends
     * SplFileInfo, and Laravel's `required` rule asks a file for `getPath()`,
     * which SplFileInfo answers with the containing *directory*. For a bare
     * relative filename that is an empty string, so the rule fails and every
     * upload is rejected as though no file had been sent at all.
     */
    private function upload(string $path): UploadedFile
    {
        return new UploadedFile(
            realpath($path) ?: $path,
            basename($path),
            'image/png',
            UPLOAD_ERR_OK,
            true
        );
    }

    /**
     * Render a code onto a 200dpi A4 page at the template's position.
     *
     * The QR is placed top right at 120px, which is what 16mm comes to at 200dpi,
     * so this is the case that is genuinely hard rather than a convenient one.
     *
     * Returns the path rather than an uploaded file, because
     * `Illuminate\Http\Testing\File::create` copies its input into a temp
     * location, and the direct reader test needs the bytes where they were
     * actually drawn.
     */
    private function scanCarrying(string $code, string $path = 'scan.png', int $noise = 0): string
    {
        $payload = app(SheetCodeService::class)->encode(
            (static function () use ($code) {
                $script = new Script(['code' => $code]);
                $script->id = 1;

                return $script;
            })(),
            1,
            1
        );

        $options = new QROptions;
        $options->outputType = QROutputInterface::GDIMAGE_PNG;
        $options->eccLevel = EccLevel::H;
        (new QRCode($options))->render($payload, $path.'.qr.png');

        $codeImage = imagecreatefrompng($path.'.qr.png');

        $page = imagecreatetruecolor(1654, 2339);
        $white = imagecolorallocate($page, 252, 251, 248);
        imagefilledrectangle($page, 0, 0, 1654, 2339, $white);

        // Top right, as the template places it.
        $size = 120;
        imagecopyresampled($page, $codeImage, 1300, 200, 0, 0, $size, $size, imagesx($codeImage), imagesy($codeImage));

        // Optional handwriting-like marks, so the code is not alone on a blank
        // page. A real scan never has it to itself.
        if ($noise > 0) {
            $ink = imagecolorallocate($page, 25, 45, 140);
            for ($i = 0; $i < $noise; $i++) {
                $x = random_int(90, 1200);
                $y = random_int(400, 2100);
                imageline($page, $x, $y, $x + random_int(30, 90), $y + random_int(-5, 5), $ink);
            }
        }

        imagepng($page, $path);

        return $path;
    }

    // --- the reader itself ---------------------------------------------------

    public function test_it_reads_a_code_off_a_realistic_scan(): void
    {
        $identifier = app(ScanIdentifier::class);
        $path = $this->scanCarrying('CG-00042-ABCDEF', 'idn_ok.png', 250);

        $found = $identifier->identifyFromPath($path, 'image/png');

        $this->assertNotNull($found, 'The quick single-attempt search must read our own template');
        $this->assertSame('CG-00042-ABCDEF', $found['code']);
        $this->assertSame(1, $found['page']);
    }

    public function test_a_page_with_no_code_is_reported_as_unidentified_not_failed(): void
    {
        $identifier = app(ScanIdentifier::class);

        $page = imagecreatetruecolor(1654, 2339);
        $white = imagecolorallocate($page, 255, 255, 255);
        imagefilledrectangle($page, 0, 0, 1654, 2339, $white);
        $ink = imagecolorallocate($page, 20, 40, 140);
        for ($i = 0; $i < 300; $i++) {
            $x = random_int(80, 1500);
            $y = random_int(80, 2200);
            imageline($page, $x, $y, $x + random_int(-40, 90), $y + random_int(-6, 6), $ink);
        }
        imagepng($page, 'idn_none.png');

        // IDN-05: an unreadable page is kept, never refused and never an error.
        $this->assertNull($identifier->identifyFromPath('idn_none.png', 'image/png'));
    }

    public function test_a_pdf_upload_is_left_for_page_assembly_rather_than_failing(): void
    {
        $identifier = app(ScanIdentifier::class);

        // No file needed: a PDF is rejected before any decoding is attempted.
        $this->assertNull($identifier->identifyFromPath('does-not-exist.pdf', 'application/pdf'));
    }

    // --- attaching a scan to the sheet it came from ---------------------------

    public function test_a_scan_attaches_to_the_issued_sheet_and_inherits_its_candidate(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);

        // Issue the sheet first, exactly as a school would before the exam.
        $sheet = $this->postJson("/api/v1/exams/{$exam->id}/sheets", [
            'student_id' => $studentId,
        ], $this->headers)->assertCreated()->json('sheet');

        $file = $this->upload($this->scanCarrying($sheet['code'], 'idn_attach.png', 200));

        $response = $this->post("/api/v1/exams/{$exam->id}/scripts", [
            'file' => $file,
        ], $this->uploadHeaders())->assertCreated();

        $scriptId = $response->json('script.id');

        // The critical assertion: the scan landed on the row the sheet created,
        // rather than making a second row for one paper.
        $this->assertSame($sheet['script_id'], $scriptId);
        $this->assertSame(1, Script::where('exam_id', $exam->id)->count());

        $script = Script::findOrFail($scriptId);
        $this->assertSame($studentId, $script->student_id, 'The candidate comes from the sheet, not the upload');
        $this->assertNotNull($script->original_path);
        $this->assertSame(Script::STATUS_UPLOADED, $script->status);
    }

    public function test_an_unreadable_scan_still_uploads_and_is_kept_for_the_exception_queue(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);

        // A page with handwriting and no readable code.
        $page = imagecreatetruecolor(1654, 2339);
        $white = imagecolorallocate($page, 255, 255, 255);
        imagefilledrectangle($page, 0, 0, 1654, 2339, $white);
        $ink = imagecolorallocate($page, 20, 40, 140);
        for ($i = 0; $i < 300; $i++) {
            $x = random_int(80, 1500);
            $y = random_int(80, 2200);
            imageline($page, $x, $y, $x + random_int(-40, 90), $y + random_int(-6, 6), $ink);
        }
        imagepng($page, 'idn_upload_none.png');

        $response = $this->post("/api/v1/exams/{$exam->id}/scripts", [
            'file' => $this->upload('idn_upload_none.png'),
            'student_id' => $studentId,
        ], $this->uploadHeaders())->assertCreated();

        $script = Script::findOrFail($response->json('script.id'));
        $this->assertSame($studentId, $script->student_id);
        $this->assertSame(Script::STATUS_UPLOADED, $script->status);
    }

    public function test_a_code_from_another_paper_does_not_attach_here(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);
        $this->postJson("/api/v1/exams/{$exam->id}/sheets", ['student_id' => $studentId], $this->uploadHeaders())
            ->assertCreated();

        // A code this examination never issued. The signature verifies, because
        // it is a real code, but it belongs to no row here.
        $foreign = 'CG-99999-DEADBE';
        $file = $this->upload($this->scanCarrying($foreign, 'idn_foreign.png', 100));

        $response = $this->post("/api/v1/exams/{$exam->id}/scripts", [
            'file' => $file,
        ], $this->uploadHeaders())->assertCreated();

        $scriptId = $response->json('script.id');
        $script = Script::findOrFail($scriptId);

        // No match, so a fresh unidentified row is created rather than stealing
        // another paper's script.
        $this->assertNotSame($foreign, $script->code);
        $this->assertNull($script->student_id);
        $this->assertNotNull($script->flag_note, 'An unmatched scan must be flagged for the exception queue');
    }

    public function test_a_superseded_code_does_not_attach_to_the_reissued_sheet(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);

        $first = $this->postJson("/api/v1/exams/{$exam->id}/sheets", [
            'student_id' => $studentId,
        ], $this->headers)->assertCreated()->json('sheet');

        // Reissue, which retires the first code.
        $this->postJson("/api/v1/exams/{$exam->id}/sheets/{$first['script_id']}/reissue", [
            'reason' => 'Misprinted.',
        ], $this->uploadHeaders())->assertCreated();

        $current = Script::findOrFail($first['script_id'])->code;
        $this->assertNotSame($first['code'], $current);

        // A scan of the RETIRED sheet must not attach to the reissued row.
        $retired = $this->upload($this->scanCarrying($first['code'], 'idn_retired.png', 100));
        $this->post("/api/v1/exams/{$exam->id}/scripts", ['file' => $retired], $this->uploadHeaders())
            ->assertCreated();

        $script = Script::findOrFail($first['script_id']);
        $this->assertNull(
            $script->original_path,
            'A withdrawn code must not attach a scan, or the reissue would be meaningless'
        );
    }

    public function test_another_school_cannot_attach_a_scan_to_our_candidate(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);
        $sheet = $this->postJson("/api/v1/exams/{$exam->id}/sheets", [
            'student_id' => $studentId,
        ], $this->headers)->assertCreated()->json('sheet');

        \Illuminate\Support\Facades\Auth::forgetGuards();

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

        // They cannot even reach our examination, so no code of ours resolves.
        $file = $this->upload($this->scanCarrying($sheet['code'], 'idn_tenant.png', 100));

        $this->post("/api/v1/exams/{$exam->id}/scripts", ['file' => $file], $headers)
            ->assertNotFound();

        $this->assertNull(Script::findOrFail($sheet['script_id'])->original_path);
    }

    public function test_the_upload_is_not_held_open_by_a_slow_search(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);

        // A page with no code is the slow case, and it must still be quick
        // because the capture path only makes one attempt.
        $page = imagecreatetruecolor(1654, 2339);
        $white = imagecolorallocate($page, 255, 255, 255);
        imagefilledrectangle($page, 0, 0, 1654, 2339, $white);
        imagepng($page, 'idn_timing.png');

        $started = microtime(true);

        $this->post("/api/v1/exams/{$exam->id}/scripts", [
            'file' => $this->upload('idn_timing.png'),
            'student_id' => $studentId,
        ], $this->uploadHeaders())->assertCreated();

        $elapsed = microtime(true) - $started;

        $this->assertLessThan(
            12.0,
            $elapsed,
            'Identification must not turn an upload into a long wait. The thorough search runs in the queue.'
        );
    }
}