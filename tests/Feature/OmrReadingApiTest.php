<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ExamQuestion;
use App\Models\Script;
use App\Services\OmrLayout;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\WritesScratchImages;
use Tests\Feature\ScriptMarkingTestCase;

/**
 * The objective readings the API hands a marker (OMR-02 to OMR-06).
 *
 * These build a real sheet, fill it in the way a pencil would, capture it through
 * the real upload endpoint and then ask the API what the candidate answered. The
 * point is the last step: the readings are only worth anything if the marking
 * screen receives them, and a service that works but is never wired up is a
 * feature nobody has.
 */
final class OmrReadingApiTest extends ScriptMarkingTestCase
{
    use WritesScratchImages;

    /**
     * An exam whose questions are all multiple choice, with an answer key.
     *
     * @return array{0:int,1:array<int,string>} exam id, then question number to letter
     */
    private function objectiveExam(int $questionCount = 3): array
    {
        $courseId = $this->postJson('/api/v1/course-units', [
            'code' => 'PHY401',
            'title' => 'Physics',
        ], $this->headers)->json('id');

        $examId = $this->postJson('/api/v1/exams', [
            'title' => 'Objective Only Examination',
            'type' => 'end_of_term',
            'course_unit_id' => $courseId,
            'exam_date' => '2026-10-01',
            'status' => 'marking',
        ], $this->headers)->json('id');

        $key = [];

        // A different letter per question, cycling, so a reader that always
        // answered the same column would fail rather than pass by luck. Cycles
        // because a long paper is more than four questions.
        $letters = ['A', 'C', 'B', 'D'];

        for ($number = 1; $number <= $questionCount; $number++) {
            $this->postJson("/api/v1/exams/{$examId}/questions", [
                'number' => $number,
                'prompt' => "Objective question {$number}",
                'kind' => 'multiple_choice',
                'max_mark' => 1,
                'granularity' => 1,
                'options' => ['alpha', 'beta', 'gamma', 'delta'],
            ], $this->headers)->assertCreated();

            $key[$number] = $letters[($number - 1) % count($letters)];
        }

        return [$examId, $key];
    }

    /**
     * A filled-in objective sheet at 300dpi, with the QR the capture endpoint
     * needs in order to attach it to a candidate.
     */
    private function filledScan(Script $script, array $answersByQuestionNumber): UploadedFile
    {
        $scale = OmrReadingServiceForTests::DPI / 25.4;

        $image = imagecreatetruecolor(
            (int) round(OmrLayout::PAGE_WIDTH_MM * $scale),
            (int) round(OmrLayout::PAGE_HEIGHT_MM * $scale)
        );
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 0, 0, imagesx($image), imagesy($image), $white);

        // The identifier crops to the QR, so the code has to be on the page for
        // the capture to attach to this script rather than arriving unidentified.
        // Top right, because that is where the sheet puts the code and where
        // ScanIdentifier looks first. The existing identification tests already
        // cover the other regions.
        $this->drawCode($image, $script, $scale);

        $questions = ExamQuestion::where('exam_id', $script->exam_id)
            ->where('kind', ExamQuestion::KIND_MULTIPLE_CHOICE)
            ->get();
        $layout = OmrLayout::forQuestions($questions);

        $black = imagecolorallocate($image, 0, 0, 0);

        foreach ($questions as $question) {
            $letter = $answersByQuestionNumber[$question->number] ?? null;

            if ($letter === null) {
                continue;
            }

            $point = $layout[$question->id]['options'][$letter];
            $radius = (int) round(((OmrLayout::BUBBLE_DIAMETER_MM / 2) * 0.8) * $scale);

            imagefilledellipse(
                $image,
                (int) round($point['x'] * $scale),
                (int) round($point['y'] * $scale),
                $radius * 2,
                $radius * 2,
                $black
            );
        }

        $path = $this->scratch('objective_filled.png');
        imagepng($image, $path);

        return new UploadedFile($path, 'objective.png', 'image/png', UPLOAD_ERR_OK, true);
    }

    private function drawCode(\GdImage $image, Script $script, float $scale): void
    {
        $payload = app(\App\Services\SheetCodeService::class)->encode($script, 1, 1);

        $options = new \chillerlan\QRCode\QROptions;
        $options->outputType = \chillerlan\QRCode\Output\QROutputInterface::GDIMAGE_PNG;
        $options->eccLevel = \chillerlan\QRCode\Common\EccLevel::H;

        $codePath = $this->scratch('objective_code.png');
        (new \chillerlan\QRCode\QRCode($options))->render($payload, $codePath);

        $code = imagecreatefrompng($codePath);

        // 16mm, which is the size the sheet prints it at, placed inside the
        // top-right region the identifier crops first.
        $side = (int) round(16 * $scale);
        $left = (int) round((imagesx($image) * 0.86) - ($side / 2));
        $top = (int) round((imagesy($image) * 0.08) - ($side / 2));

        imagecopyresampled($image, $code, $left, $top,
            0, 0, $side, $side, imagesx($code), imagesy($code));
    }

    public function test_the_api_returns_what_the_candidate_filled_in(): void
    {
        [$examId, $key] = $this->objectiveExam();

        $studentId = $this->makeStudentForExam($examId);
        $sheet = $this->postJson("/api/v1/exams/{$examId}/sheets", [
            'student_id' => $studentId,
        ], $this->headers)->assertCreated()->json('sheet');

        $script = Script::findOrFail($sheet['script_id']);
        $script->forceFill(['page_count' => 1])->save();

        $this->post("/api/v1/exams/{$examId}/scripts", [
            'file' => $this->filledScan($script, $key),
            'student_id' => $studentId,
        ], $this->headers)->assertCreated();

        $response = $this->getJson("/api/v1/scripts/{$script->id}", $this->headers)
            ->assertOk()
            ->json('omr');

        $this->assertCount(3, $response, 'Every bubbled question should come back with a reading');

        foreach ($response as $reading) {
            $this->assertSame('marked', $reading['status']);
            $this->assertSame(
                $key[$reading['number']],
                $reading['option'],
                "Question {$reading['number']} was answered {$key[$reading['number']]}"
            );
            $this->assertNotNull($reading['confidence']);
            $this->assertIsInt($reading['number'], 'A marker reads question numbers, not row ids');
        }
    }

    public function test_an_unanswered_question_is_reported_blank_rather_than_omitted(): void
    {
        [$examId] = $this->objectiveExam();

        $studentId = $this->makeStudentForExam($examId);
        $sheet = $this->postJson("/api/v1/exams/{$examId}/sheets", [
            'student_id' => $studentId,
        ], $this->headers)->assertCreated()->json('sheet');

        $script = Script::findOrFail($sheet['script_id']);
        $script->forceFill(['page_count' => 1])->save();

        // Question 1 answered, the rest left empty.
        $this->post("/api/v1/exams/{$examId}/scripts", [
            'file' => $this->filledScan($script, [1 => 'A']),
            'student_id' => $studentId,
        ], $this->headers)->assertCreated();

        $readings = $this->getJson("/api/v1/scripts/{$script->id}", $this->headers)
            ->assertOk()
            ->json('omr');

        $byNumber = collect($readings)->keyBy('number');

        $this->assertSame('marked', $byNumber[1]['status']);

        // Left out entirely, a blank would be indistinguishable from a question
        // the machine simply did not look at.
        foreach ([2, 3] as $number) {
            $this->assertSame('blank', $byNumber[$number]['status'], "Question {$number}");
            $this->assertNull($byNumber[$number]['option']);
        }
    }

    public function test_a_written_only_exam_reports_no_readings(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);

        $sheet = $this->postJson("/api/v1/exams/{$exam->id}/sheets", [
            'student_id' => $studentId,
        ], $this->headers)->assertCreated()->json('sheet');

        $script = Script::findOrFail($sheet['script_id']);

        // No scan, so there is nothing to read, and the key must be absent rather
        // than present and empty so a client can tell the two cases apart.
        $body = $this->getJson("/api/v1/scripts/{$script->id}", $this->headers)->assertOk()->json();

        $this->assertArrayNotHasKey('omr', $body);
    }

    public function test_an_objective_section_too_long_for_one_capture_reports_nothing(): void
    {
        // More questions than fit on one objective page, so the section needs a
        // second page that a single capture cannot supply.
        //
        // The readings must be absent rather than partial. A partial summary reads
        // "0 of 26 filled" for questions whose page was never looked at, and a
        // marker would reasonably take that as the candidate having skipped them.
        [$examId] = $this->objectiveExam(OmrLayout::QUESTIONS_PER_PAGE + 4);

        $studentId = $this->makeStudentForExam($examId);
        $sheet = $this->postJson("/api/v1/exams/{$examId}/sheets", [
            'student_id' => $studentId,
        ], $this->headers)->assertCreated()->json('sheet');

        $script = Script::findOrFail($sheet['script_id']);
        $script->forceFill(['page_count' => 1])->save();

        $this->post("/api/v1/exams/{$examId}/scripts", [
            'file' => $this->filledScan($script, [1 => 'A']),
            'student_id' => $studentId,
        ], $this->headers)->assertCreated();

        $body = $this->getJson("/api/v1/scripts/{$script->id}", $this->headers)->assertOk()->json();

        $this->assertArrayNotHasKey(
            'omr',
            $body,
            'A partial objective summary implies the candidate skipped questions nobody looked at'
        );
    }

    private function makeStudentForExam(int $examId): int
    {
        $exam = \App\Models\Exam::findOrFail($examId);

        return $this->makeStudent($exam);
    }
}

/**
 * Keeps the DPI used to build fixtures in one place.
 *
 * It has to match OmrReadingService, because a fixture rendered at a different
 * resolution than the reader is told to assume would be testing nothing.
 */
final class OmrReadingServiceForTests
{
    public const DPI = \App\Services\OmrReadingService::DEFAULT_DPI;

}
