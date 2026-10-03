<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\ExamQuestion;
use App\Services\BubbleReader;
use App\Services\OmrLayout;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BubbleReaderTest extends TestCase
{
    private const DPI = 300;

    private function question(int $id, int $number, array $options = ['a', 'b', 'c', 'd']): ExamQuestion
    {
        $question = new ExamQuestion;
        $question->id = $id;
        $question->number = $number;
        $question->kind = ExamQuestion::KIND_MULTIPLE_CHOICE;
        $question->options = ['options' => $options];

        return $question;
    }

    /**
     * A blank sheet at the printed size, with every bubble drawn as the template
     * draws it: a ring, white inside.
     */
    private function blankSheet(\Illuminate\Support\Collection $questions): \GdImage
    {
        $scale = self::DPI / 25.4;
        $image = imagecreatetruecolor(
            (int) round(OmrLayout::PAGE_WIDTH_MM * $scale),
            (int) round(OmrLayout::PAGE_HEIGHT_MM * $scale)
        );

        $white = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 0, 0, imagesx($image), imagesy($image), $white);

        $black = imagecolorallocate($image, 0, 0, 0);
        $layout = OmrLayout::forQuestions($questions);

        foreach ($layout as $entry) {
            foreach ($entry['options'] as $point) {
                $this->drawBubble($image, $point['x'] * $scale, $point['y'] * $scale, $black, $white, 0);
            }
        }

        return $image;
    }

    /**
     * Draw one bubble, optionally filled.
     *
     * Filled means a soft blot covering roughly 80% of the interior, which is what
     * a pencil drawn back and forth leaves. Perfectly covering the disc would be
     * too clean to be a fair test.
     */
    private function drawBubble(
        \GdImage $image,
        float $cx,
        float $cy,
        int $black,
        int $white,
        float $fillFraction,
    ): void {
        $scale = self::DPI / 25.4;
        $outer = (OmrLayout::BUBBLE_DIAMETER_MM / 2) * $scale;
        $thickness = max(1, (int) round(0.35 * $scale));

        imagesetthickness($image, $thickness);
        imageellipse($image, (int) round($cx), (int) round($cy), (int) round($outer * 2), (int) round($outer * 2), $black);

        if ($fillFraction > 0) {
            $inner = $outer * $fillFraction;
            imagefilledellipse($image, (int) round($cx), (int) round($cy), (int) round($inner * 2), (int) round($inner * 2), $black);
        }

        imagesetthickness($image, 1);
    }

    /** @param array<string, string> $answers  question id to option letter */
    private function sheetWithAnswers(\Illuminate\Support\Collection $questions, array $answers, float $fillFraction = 0.8): \GdImage
    {
        $scale = self::DPI / 25.4;
        $image = $this->blankSheet($questions);
        $black = imagecolorallocate($image, 0, 0, 0);
        $layout = OmrLayout::forQuestions($questions);

        foreach ($answers as $questionId => $letter) {
            $point = $layout[$questionId]['options'][$letter];
            $this->drawBubble($image, $point['x'] * $scale, $point['y'] * $scale, $black, $black, $fillFraction);
        }

        return $image;
    }

    #[Test]
    public function an_unfilled_sheet_reads_as_blank_not_as_marked(): void
    {
        // The failure this guards against: the printed ring is ink on every
        // sheet, so a reader that samples the whole disc sees darkness
        // everywhere and reports every bubble filled.
        $questions = collect([$this->question(1, 1), $this->question(2, 2)]);
        $image = $this->blankSheet($questions);

        $readings = app(BubbleReader::class)->readPage($image, OmrLayout::forQuestions($questions), 1, self::DPI);

        $this->assertCount(2, $readings);
        foreach ($readings as $reading) {
            $this->assertSame(BubbleReader::ANSWER_BLANK, $reading['status']);
            $this->assertNull($reading['option']);
        }
    }

    #[Test]
    public function it_recovers_the_answers_a_candidate_actually_marked(): void
    {
        $questions = collect([
            $this->question(1, 1),
            $this->question(2, 2),
            $this->question(3, 3),
            $this->question(4, 4, ['a', 'b', 'c']),
        ]);
        $expected = ['1' => 'A', '2' => 'C', '3' => 'D', '4' => 'B'];
        $image = $this->sheetWithAnswers($questions, $expected);

        $readings = app(BubbleReader::class)->readPage($image, OmrLayout::forQuestions($questions), 1, self::DPI);

        foreach ($expected as $questionId => $letter) {
            $this->assertSame(
                $letter,
                $readings[(int) $questionId]['option'],
                "Question {$questionId} was marked {$letter}"
            );
            $this->assertSame(BubbleReader::ANSWER_MARKED, $readings[(int) $questionId]['status']);
            $this->assertGreaterThan(0, $readings[(int) $questionId]['confidence']);
        }
    }

    #[Test]
    public function a_letter_option_chosen_for_each_question_is_not_confused_with_a_neighbour(): void
    {
        // Column confusion is the classic OMR failure: reading C as B because
        // the sample drifted a column. Each question gets a different column so a
        // systematic offset would show up as every answer being wrong by one.
        $questions = collect(range(1, 4))->map(fn (int $i) => $this->question($i, $i));
        $expected = ['1' => 'A', '2' => 'B', '3' => 'C', '4' => 'D'];
        $image = $this->sheetWithAnswers($questions, $expected);

        $readings = app(BubbleReader::class)->readPage($image, OmrLayout::forQuestions($questions), 1, self::DPI);

        foreach ($expected as $questionId => $letter) {
            $this->assertSame($letter, $readings[(int) $questionId]['option'], "Question {$questionId}");
        }
    }

    #[Test]
    public function two_marked_bubbles_on_one_question_are_ambiguous_not_a_guess(): void
    {
        $questions = collect([$this->question(1, 1)]);
        $image = $this->sheetWithAnswers($questions, ['1' => 'A']);

        // Candidate changed their mind without erasing.
        $scale = self::DPI / 25.4;
        $layout = OmrLayout::forQuestions($questions);
        $black = imagecolorallocate($image, 0, 0, 0);
        $point = $layout[1]['options']['C'];
        $this->drawBubble($image, $point['x'] * $scale, $point['y'] * $scale, $black, $black, 0.8);

        $readings = app(BubbleReader::class)->readPage($image, $layout, 1, self::DPI);

        $this->assertSame(BubbleReader::ANSWER_AMBIGUOUS, $readings[1]['status']);
        $this->assertNull($readings[1]['option'], 'A contradiction must not be resolved by picking one');
    }

    #[Test]
    public function a_lightly_touched_bubble_is_ambiguous_rather_than_silently_wrong(): void
    {
        $questions = collect([$this->question(1, 1)]);
        $image = $this->sheetWithAnswers($questions, ['1' => 'B'], fillFraction: 0.30);

        $readings = app(BubbleReader::class)->readPage($image, OmrLayout::forQuestions($questions), 1, self::DPI);

        $this->assertSame(
            BubbleReader::ANSWER_AMBIGUOUS,
            $readings[1]['status'],
            'Ink in the grey band is a question for a marker, not a coin toss'
        );
    }

    #[Test]
    public function a_faintly_filled_bubble_is_still_read_as_marked(): void
    {
        // The other side of the band. A candidate who shaded a bubble lightly
        // has given a real answer and must not be sent to a marker.
        $questions = collect([$this->question(1, 1)]);
        $image = $this->sheetWithAnswers($questions, ['1' => 'B'], fillFraction: 0.72);

        $readings = app(BubbleReader::class)->readPage($image, OmrLayout::forQuestions($questions), 1, self::DPI);

        $this->assertSame(BubbleReader::ANSWER_MARKED, $readings[1]['status']);
        $this->assertSame('B', $readings[1]['option']);
    }

    #[Test]
    public function a_heavily_marked_bubble_is_still_read_as_marked(): void
    {
        // A candidate who scribbled over the bubble to be certain must not be
        // penalised for enthusiasm.
        $questions = collect([$this->question(1, 1)]);
        $image = $this->sheetWithAnswers($questions, ['1' => 'D'], fillFraction: 0.98);

        $readings = app(BubbleReader::class)->readPage($image, OmrLayout::forQuestions($questions), 1, self::DPI);

        $this->assertSame('D', $readings[1]['option']);
        $this->assertSame(BubbleReader::ANSWER_MARKED, $readings[1]['status']);
    }

    #[Test]
    public function it_reads_only_the_page_asked_for(): void
    {
        $questions = collect(range(1, OmrLayout::QUESTIONS_PER_PAGE + 4))
            ->map(fn (int $i) => $this->question($i, $i));
        $image = $this->sheetWithAnswers($questions, ['1' => 'A', (string) (OmrLayout::QUESTIONS_PER_PAGE + 4) => 'B']);

        $layout = OmrLayout::forQuestions($questions);
        $reader = app(BubbleReader::class);

        $pageOne = $reader->readPage($image, $layout, 1, self::DPI);
        $pageTwo = $reader->readPage($image, $layout, 2, self::DPI);

        // A question on page two must not be reported when reading page one, or a
        // candidate's answer would be attributed to whatever question happens to
        // sit at that slot on the page in front of us.
        $this->assertArrayNotHasKey(OmrLayout::QUESTIONS_PER_PAGE + 4, $pageOne);
        $this->assertArrayHasKey(1, $pageOne);
        $this->assertArrayHasKey(OmrLayout::QUESTIONS_PER_PAGE + 4, $pageTwo);
        $this->assertSame('B', $pageTwo[OmrLayout::QUESTIONS_PER_PAGE + 4]['option']);
    }

    #[Test]
    public function a_sheet_survives_a_change_in_scan_resolution(): void
    {
        // Schools scan at whatever their machine does. The layout is in
        // millimetres precisely so the reader can be told the resolution rather
        // than guessing it, and it has to hold at each one.
        $questions = collect([$this->question(1, 1), $this->question(2, 2)]);

        foreach ([200, 300, 400] as $dpi) {
            $scale = $dpi / 25.4;
            $image = imagecreatetruecolor(
                (int) round(OmrLayout::PAGE_WIDTH_MM * $scale),
                (int) round(OmrLayout::PAGE_HEIGHT_MM * $scale)
            );
            $white = imagecolorallocate($image, 255, 255, 255);
            imagefilledrectangle($image, 0, 0, imagesx($image), imagesy($image), $white);
            $black = imagecolorallocate($image, 0, 0, 0);

            $layout = OmrLayout::forQuestions($questions);
            foreach ($layout as $entry) {
                foreach ($entry['options'] as $point) {
                    $this->drawBubble($image, $point['x'] * $scale, $point['y'] * $scale, $black, $white, 0);
                }
            }
            $filled = $layout[1]['options']['C'];
            $this->drawBubble($image, $filled['x'] * $scale, $filled['y'] * $scale, $black, $black, 0.8);

            $readings = app(BubbleReader::class)->readPage($image, $layout, 1, $dpi);

            $this->assertSame('C', $readings[1]['option'], "Wrong answer read at {$dpi}dpi");
            $this->assertSame(BubbleReader::ANSWER_BLANK, $readings[2]['status'], "Blank misread at {$dpi}dpi");
        }
    }

    #[Test]
    public function it_reads_a_palette_image_the_same_as_a_truecolour_one(): void
    {
        // Regression, and the reason the end-to-end check exists.
        //
        // imagecolorat() returns a palette INDEX rather than a colour on a
        // palette image. Reading one as though it were RGB turns white paper into
        // black, so every bubble on a blank sheet came back completely filled and
        // every candidate scored full marks on an untouched paper.
        //
        // Every other test here builds its image with imagecreatetruecolor, so
        // none of them could ever have caught it. They agreed with each other and
        // with a wrong answer.
        $questions = collect([$this->question(1, 1), $this->question(2, 2)]);
        $marked = ['1' => 'C', '2' => 'A'];

        $truecolour = $this->sheetWithAnswers($questions, $marked);

        $path = tempnam(sys_get_temp_dir(), 'omr_pal').'.png';
        imagepng($truecolour, $path);

        $palette = imagecreatefrompng($path);
        // Forced explicitly. A sheet that is pure black and white is small enough
        // that the PNG may come back truecolour, and then this test would pass
        // without ever exercising the bug it exists for.
        imagetruecolortopalette($palette, false, 4);
        $this->assertFalse(
            imageistruecolor($palette),
            'The fixture must actually be a palette image or this test proves nothing'
        );

        $reader = app(BubbleReader::class);
        $layout = OmrLayout::forQuestions($questions);

        $fromPalette = $reader->readPage($palette, $layout, 1, self::DPI);
        $fromTruecolour = $reader->readPage($truecolour, $layout, 1, self::DPI);

        $this->assertSame(
            'C',
            $fromPalette[1]['option'],
            'A palette image must read the same as a truecolour one'
        );
        $this->assertSame($fromTruecolour, $fromPalette);
    }

    #[Test]
    public function a_blank_palette_image_does_not_read_as_every_answer_given(): void
    {
        // The same bug stated as the harm it causes.
        $questions = collect([$this->question(1, 1)]);
        $path = tempnam(sys_get_temp_dir(), 'omr_pal2').'.png';
        imagepng($this->blankSheet($questions), $path);

        $palette = imagecreatefrompng($path);
        $readings = app(BubbleReader::class)->readPage($palette, OmrLayout::forQuestions($questions), 1, self::DPI);

        $this->assertSame(BubbleReader::ANSWER_BLANK, $readings[1]['status']);
    }
}
