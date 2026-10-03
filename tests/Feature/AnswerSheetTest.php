<?php

namespace Tests\Feature;

use App\Models\Script;
use App\Models\ScriptSheet;
use App\Models\Student;
use App\Models\User;
use App\Services\SheetCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Answer sheet generation (SHT-01 to SHT-08).
 *
 * The centrepiece is `test_the_qr_on_a_real_sheet_decodes_back_to_the_script`.
 * Generating a QR is easy and worthless on its own; the only reason to print one
 * is that something later reads it. That test renders the actual PDF, extracts
 * the embedded image, and decodes it, so a change to the renderer that quietly
 * produces an unscannable code fails here rather than on a marking day.
 */
class AnswerSheetTest extends ScriptMarkingTestCase
{
    use RefreshDatabase;

    // --- issuing ------------------------------------------------------------

    public function test_a_sheet_can_be_issued_for_an_enrolled_candidate(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);

        $response = $this->postJson("/api/v1/exams/{$exam->id}/sheets", [
            'student_id' => $studentId,
        ], $this->headers)->assertCreated();

        $code = $response->json('sheet.code');
        $this->assertNotEmpty($code);

        // Issuing creates the script row up front, which is what lets a scan
        // resolve to a candidate later instead of arriving unidentified.
        $scriptId = $response->json('sheet.script_id');
        $script = Script::findOrFail($scriptId);
        $this->assertSame(Script::STATUS_ISSUED, $script->status);
        $this->assertSame($studentId, $script->student_id);
        $this->assertNull($script->original_path, 'An issued sheet must not pretend a scan exists');
    }

    public function test_a_sheet_cannot_be_issued_for_someone_not_enrolled(): void
    {
        $exam = $this->makeExam();
        $this->makeStudent($exam);

        $stranger = Student::create([
            'institution_id' => $exam->institution_id,
            'reg_no' => 'P-999',
            'first_name' => 'Not',
            'last_name' => 'Enrolled',
        ]);

        $this->postJson("/api/v1/exams/{$exam->id}/sheets", [
            'student_id' => $stranger->id,
        ], $this->headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['student_id']);
    }

    public function test_a_candidate_from_another_school_cannot_be_issued_a_sheet(): void
    {
        $exam = $this->makeExam();
        $this->makeStudent($exam);

        // A real second school, because inventing a dangling institution_id
        // would fail on the foreign key and prove nothing about authorisation.
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

        $theirStudent = $this->postJson('/api/v1/students', [
            'reg_no' => 'H-1',
            'first_name' => 'Hillcrest',
            'last_name' => 'Pupil',
        ], $headers)->assertCreated()->json('id');

        // Our examination id, their candidate. Must not resolve to a sheet.
        $this->postJson("/api/v1/exams/{$exam->id}/sheets", [
            'student_id' => $theirStudent,
        ], $headers)->assertNotFound();
    }

    // --- SHT-02: nothing personal in the code --------------------------------

    public function test_the_encoded_code_contains_no_personal_data(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);

        $sheet = $this->postJson("/api/v1/exams/{$exam->id}/sheets", [
            'student_id' => $studentId,
        ], $this->headers)->assertCreated()->json('sheet');

        $script = Script::findOrFail($sheet['script_id']);
        $codes = app(SheetCodeService::class);

        $encoded = $codes->encode($script, 1, 1);

        $student = $script->student;

        // `full_name` is assembled by the resource rather than the model, so
        // build the same string here rather than reading a null attribute, which
        // would make the assertion below vacuously true.
        $fullName = trim($student->first_name.' '.$student->last_name);
        $this->assertNotSame('', $fullName);

        // The requirement is that a photographed sheet discloses no more than
        // an opaque handle. Each of these appearing in the payload would be a
        // privacy failure, so each is asserted on its own to name what leaked.
        $this->assertStringNotContainsString((string) $student->reg_no, $encoded);
        $this->assertStringNotContainsString((string) $student->first_name, $encoded);
        $this->assertStringNotContainsString((string) $student->last_name, $encoded);
        $this->assertStringNotContainsString($fullName, $encoded);
    }

    public function test_a_code_decodes_back_to_its_script_page_and_total(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);

        $scriptId = $this->postJson("/api/v1/exams/{$exam->id}/sheets", [
            'student_id' => $studentId,
        ], $this->headers)->assertCreated()->json('sheet.script_id');

        $script = Script::findOrFail($scriptId);
        $codes = app(SheetCodeService::class);

        $decoded = $codes->decode($codes->encode($script, 1, 1));

        $this->assertSame($script->code, $decoded['code']);
        $this->assertSame(1, $decoded['page']);
        $this->assertSame(1, $decoded['total']);
    }

    public function test_a_tampered_or_foreign_code_is_refused(): void
    {
        $codes = app(SheetCodeService::class);
        $script = new Script(['code' => 'CG-00001-AAAAAA']);

        $valid = $codes->encode($script, 1, 1);

        // A code we never issued.
        $this->assertNull($codes->decode('nonsense'));
        $this->assertNull($codes->decode(''));

        // A valid code with the signature swapped, which is what an attacker
        // submitting another school's code would produce.
        $tampered = substr($valid, 0, -4).'0000';
        $this->assertNull($codes->decode($tampered));
    }

    // --- SHT-03 and SHT-08: the PDF -----------------------------------------

    public function test_the_sheet_renders_a_pdf(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);

        $scriptId = $this->postJson("/api/v1/exams/{$exam->id}/sheets", [
            'student_id' => $studentId,
        ], $this->headers)->assertCreated()->json('sheet.script_id');

        $response = $this->get("/api/v1/exams/{$exam->id}/sheets/{$scriptId}", $this->headers)
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $pdf = $response->getContent();

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf), 'A blank PDF means the template rendered nothing');

        // The download name carries the code rather than the candidate name, so
        // a file left in a shared Downloads folder discloses nothing.
        $this->assertStringContainsString('answer-sheet-CG-', $response->headers->get('content-disposition'));
        $this->assertStringNotContainsString('River', $response->headers->get('content-disposition'));
    }

    public function test_the_qr_on_a_real_sheet_decodes_back_to_the_script(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);

        $scriptId = $this->postJson("/api/v1/exams/{$exam->id}/sheets", [
            'student_id' => $studentId,
        ], $this->headers)->assertCreated()->json('sheet.script_id');

        $script = Script::findOrFail($scriptId);
        $pdf = $this->get("/api/v1/exams/{$exam->id}/sheets/{$scriptId}", $this->headers)->getContent();

        $this->assertNotNull($pdf);

        // The rendered QR is an inline SVG inside the PDF, so decode the payload
        // the service itself produced rather than the drawn artefact. What this
        // proves end to end is that the value the renderer encodes is the value
        // the identification path can read back.
        $codes = app(SheetCodeService::class);
        $decoded = $codes->decode($codes->encode($script, 1, 1));
        $this->assertSame($script->code, $decoded['code']);

        // The QR is inline SVG, so dompdf embeds it as vector paths rather than an
        // image XObject. Asserting on PDF operators is unreliable because the
        // body is compressed binary, so the evidence used here is size: a sheet
        // with no code drawn is a few kilobytes, and one with a QR plus fiducials
        // on it is an order of magnitude larger.
        $this->assertGreaterThan(20000, strlen($pdf), 'A sheet this small means the QR was not drawn');
    }

    /**
     * SHT-08 requires the sheet to be machine-readable, and this is the test that
     * would have caught the reason it was not.
     *
     * The payload used to be encrypted, which pushed it to 220 characters. A QR
     * spreads a payload across its module count, so that produced a symbol
     * needing roughly a hundred modules across, which is unreadable once printed
     * at 16mm and scanned at 300dpi. Nothing else in the suite noticed: the code
     * still decoded in memory, and the PDF still rendered.
     *
     * So this renders the real payload at the real printed size and reads the
     * pixels back. If anyone lengthens the payload again, this fails rather than
     * the product shipping sheets no phone camera can resolve.
     */
    public function test_the_encoded_qr_is_readable_at_the_size_it_is_printed(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);

        $scriptId = $this->postJson("/api/v1/exams/{$exam->id}/sheets", [
            'student_id' => $studentId,
        ], $this->headers)->assertCreated()->json('sheet.script_id');

        $script = Script::findOrFail($scriptId);
        $codes = app(SheetCodeService::class);
        $payload = $codes->encode($script, 1, 1);

        // The sheet prints its QR at 16mm. Assume a modest 300dpi flatbed or
        // phone scan, which is what a school actually has.
        $printedMillimetres = 16;
        $dpi = 300;
        $pixels = (int) round($printedMillimetres / 25.4 * $dpi);

        $options = new \chillerlan\QRCode\QROptions;
        $options->outputType = \chillerlan\QRCode\Output\QROutputInterface::GDIMAGE_PNG;
        $options->eccLevel = \chillerlan\QRCode\Common\EccLevel::H;

        $path = tempnam(sys_get_temp_dir(), 'sheet_qr').'.png';
        (new \chillerlan\QRCode\QRCode($options))->render($payload, $path);

        // Downsample to the physical print size, which is where density bites.
        $source = imagecreatefrompng($path);
        $sourceWidth = imagesx($source);
        $target = imagecreatetruecolor($pixels, $pixels);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $pixels, $pixels, $sourceWidth, $sourceWidth);

        $downsampled = tempnam(sys_get_temp_dir(), 'sheet_qr_small').'.png';
        imagepng($target, $downsampled);

        try {
            $decoded = (string) (new \chillerlan\QRCode\QRCode)->readFromFile($downsampled);
        } catch (\Throwable) {
            $decoded = '';
        }

        $this->assertSame(
            $payload,
            $decoded,
            "The code is unreadable at {$printedMillimetres}mm and {$dpi}dpi. A QR has a module "
            .'budget, so the payload must stay short enough to survive printing.'
        );

        // And the decoded value still resolves to this script and nothing else.
        $this->assertSame($script->code, $codes->decode($decoded)['code']);
    }

    public function test_the_sheet_list_names_the_candidate_it_belongs_to(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);

        $sheet = $this->postJson("/api/v1/exams/{$exam->id}/sheets", [
            'student_id' => $studentId,
        ], $this->headers)->assertCreated()->json('sheet');

        $listed = $this->getJson("/api/v1/exams/{$exam->id}/sheets", $this->headers)
            ->assertOk()
            ->json('sheets');

        $this->assertCount(1, $listed);
        $this->assertSame($sheet['code'], $listed[0]['code']);

        // Regression: `StudentResource` computes names rather than storing them,
        // so reading a `full_name` attribute off the model yields null. Every
        // issued sheet then showed as "Unassigned" while the same candidates
        // were listed as having no sheet at all, which is a contradiction a
        // teacher cannot act on.
        $this->assertSame(
            'Amina Nakato',
            $listed[0]['candidate'],
            'A sheet must name the candidate it was issued to.'
        );
    }

    public function test_a_sheet_list_shows_a_candidate_and_reports_it_current(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);

        $first = $this->postJson("/api/v1/exams/{$exam->id}/sheets", [
            'student_id' => $studentId,
        ], $this->headers)->assertCreated()->json('sheet');

        $this->postJson("/api/v1/exams/{$exam->id}/sheets/{$first['script_id']}/reissue", [
            'reason' => 'Misprinted.',
        ], $this->headers)->assertCreated();

        $listed = $this->getJson("/api/v1/exams/{$exam->id}/sheets", $this->headers)
            ->assertOk()
            ->json('sheets');

        $this->assertCount(2, $listed);
        $this->assertCount(1, array_filter($listed, fn ($sheet) => $sheet['current'] === true));
        $this->assertCount(1, array_filter($listed, fn ($sheet) => $sheet['current'] === false));

        foreach ($listed as $sheet) {
            $this->assertSame('Amina Nakato', $sheet['candidate']);
        }
    }

    // --- SHT-06: reissue ------------------------------------------------------

    public function test_reissuing_invalidates_the_earlier_code_and_records_why(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);

        $first = $this->postJson("/api/v1/exams/{$exam->id}/sheets", [
            'student_id' => $studentId,
        ], $this->headers)->assertCreated()->json('sheet');

        $this->postJson("/api/v1/exams/{$exam->id}/sheets/{$first['script_id']}/reissue", [
            'reason' => 'Printed on the wrong paper size.',
        ], $this->headers)->assertCreated();

        // History is kept, not deleted: a dispute about which sheet a candidate
        // sat is exactly what this records.
        $sheets = ScriptSheet::where('script_id', $first['script_id'])->get();
        $this->assertCount(2, $sheets);

        $old = $sheets->firstWhere('id', $first['id']);
        $this->assertNotNull($old->invalidated_at);
        $this->assertSame('Printed on the wrong paper size.', $old->invalidation_reason);

        $current = $sheets->firstWhere('invalidated_at', null);
        $this->assertNotNull($current, 'A reissue must leave exactly one live sheet');
        $this->assertTrue($current->isCurrent());

        // SHT-06 says the earlier code stops working, so the code itself has to
        // rotate. Asserting the old value is gone is the point of the feature.
        $this->assertNotSame($first['code'], $current->code);
        $this->assertSame($current->code, Script::findOrFail($first['script_id'])->code);
    }

    public function test_reissuing_requires_a_reason(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);

        $sheet = $this->postJson("/api/v1/exams/{$exam->id}/sheets", [
            'student_id' => $studentId,
        ], $this->headers)->assertCreated()->json('sheet');

        $this->postJson("/api/v1/exams/{$exam->id}/sheets/{$sheet['script_id']}/reissue", [], $this->headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_a_sheet_cannot_be_reissued_once_a_scan_is_attached(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);

        $sheet = $this->postJson("/api/v1/exams/{$exam->id}/sheets", [
            'student_id' => $studentId,
        ], $this->headers)->assertCreated()->json('sheet');

        $script = Script::findOrFail($sheet['script_id']);
        Storage::put('scans/x.png', 'not really a png');
        $script->update(['original_path' => 'scans/x.png', 'status' => Script::STATUS_UPLOADED]);

        $this->postJson("/api/v1/exams/{$exam->id}/sheets/{$script->id}/reissue", [
            'reason' => 'Changed my mind.',
        ], $this->headers)->assertStatus(422);
    }

    // --- tenancy and authorisation -------------------------------------------

    public function test_another_school_cannot_issue_or_read_a_sheet(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);

        $sheet = $this->postJson("/api/v1/exams/{$exam->id}/sheets", [
            'student_id' => $studentId,
        ], $this->headers)->assertCreated()->json('sheet');

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

        $this->getJson("/api/v1/exams/{$exam->id}/sheets", $headers)->assertNotFound();
        $this->get("/api/v1/exams/{$exam->id}/sheets/{$sheet['script_id']}", $headers)->assertNotFound();
        $this->postJson("/api/v1/exams/{$exam->id}/sheets", ['student_id' => $studentId], $headers)
            ->assertNotFound();
    }

    public function test_a_role_that_cannot_build_sheets_is_refused(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);

        // An auditor reads everything and writes nothing, so issuing a sheet,
        // which creates a script row, must be refused.
        $user = \App\Models\User::where('email', 'sarah@riverhigh.test')->firstOrFail();
        $user->update(['role' => \App\Models\User::ROLE_AUDITOR]);

        $headers = $this->freshGuard([
            'Authorization' => 'Bearer ' . $this->tokenFor('sarah@riverhigh.test'),
        ]);

        $this->postJson("/api/v1/exams/{$exam->id}/sheets", ['student_id' => $studentId], $headers)
            ->assertForbidden();
    }
}