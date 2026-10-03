<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\ExamQuestion;
use App\Services\OmrLayout;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OmrLayoutTest extends TestCase
{
    /** @param list<string> $options */
    private function question(int $id, int $number, array $options = ['a', 'b', 'c', 'd']): ExamQuestion
    {
        $question = new ExamQuestion;
        $question->id = $id;
        $question->number = $number;
        $question->kind = ExamQuestion::KIND_MULTIPLE_CHOICE;
        $question->options = ['options' => $options];

        return $question;
    }

    #[Test]
    public function bubble_coordinates_are_pure_arithmetic_from_the_row(): void
    {
        $layout = OmrLayout::forQuestions(collect([$this->question(1, 1)]));

        $first = $layout[1]['options']['A'];

        $this->assertSame(OmrLayout::GRID_LEFT_MM, $first['x']);
        $this->assertSame(OmrLayout::GRID_TOP_MM, $first['y']);

        // The whole point of objective pages is that row N does not depend on
        // anything that came before it. If this ever needs the previous row's
        // height, the geometry has stopped being addressable.
        $second = OmrLayout::forQuestions(collect([
            $this->question(1, 1),
            $this->question(2, 2),
        ]))[2]['options']['A'];

        $this->assertSame(OmrLayout::GRID_TOP_MM + OmrLayout::ROW_PITCH_MM, $second['y']);
        $this->assertSame($first['x'], $second['x']);
    }

    #[Test]
    public function only_multiple_choice_is_bubbled(): void
    {
        $written = new ExamQuestion;
        $written->id = 9;
        $written->kind = ExamQuestion::KIND_SHORT_ANSWER;

        $numeric = new ExamQuestion;
        $numeric->id = 10;
        // Numeric is objective in the model, but a candidate writes a number.
        // Bubbling it prints a grid nothing fills, and the reader scores every
        // candidate wrong on it.
        $numeric->kind = ExamQuestion::KIND_NUMERIC;
        $numeric->options = ['options' => ['1', '2', '3']];

        $this->assertSame([], OmrLayout::forQuestions(collect([$written, $numeric])));
        $this->assertSame(0, OmrLayout::pageCount(collect([$written, $numeric])));
    }

    #[Test]
    public function a_question_prints_only_as_many_bubbles_as_it_has_options(): void
    {
        $three = OmrLayout::forQuestions(collect([$this->question(1, 1, ['a', 'b', 'c'])]))[1];

        $this->assertSame(['A', 'B', 'C'], array_keys($three['options']));

        // A phantom fourth bubble is a trap. If it printed, the reader would have
        // to guess whether a mark there was a real answer or a smudge.
        $this->assertArrayNotHasKey('D', $three['options']);
    }

    #[Test]
    public function questions_flow_onto_the_next_page_and_restart_the_row_count(): void
    {
        $questions = collect(range(1, OmrLayout::QUESTIONS_PER_PAGE + 1))
            ->map(fn (int $i) => $this->question($i, $i));

        $layout = OmrLayout::forQuestions($questions);

        $this->assertSame(1, $layout[1]['page']);
        $this->assertSame(1, $layout[OmrLayout::QUESTIONS_PER_PAGE]['page']);
        $this->assertSame(OmrLayout::QUESTIONS_PER_PAGE - 1, $layout[OmrLayout::QUESTIONS_PER_PAGE]['row']);

        // The first question of page two returns to the top of the grid.
        $this->assertSame(2, $layout[OmrLayout::QUESTIONS_PER_PAGE + 1]['page']);
        $this->assertSame(0, $layout[OmrLayout::QUESTIONS_PER_PAGE + 1]['row']);
        $this->assertSame(OmrLayout::GRID_TOP_MM, $layout[OmrLayout::QUESTIONS_PER_PAGE + 1]['options']['A']['y']);

        $this->assertSame(2, OmrLayout::pageCount($questions));
    }

    #[Test]
    public function questions_are_ordered_by_number_not_by_insertion_order(): void
    {
        // Insertion order is not print order. If the layout trusted it, question
        // 7 could print above question 2 and the answer key would stop lining up.
        $layout = OmrLayout::forQuestions(collect([
            $this->question(31, 7),
            $this->question(32, 2),
        ]));

        // Sorted by number, so question 2 takes the top row and question 7 the
        // one below it, even though 7 was inserted first.
        $this->assertSame(0, $layout[32]['row']);
        $this->assertSame(1, $layout[31]['row']);
        $this->assertGreaterThan($layout[32]['options']['A']['y'], $layout[31]['options']['A']['y']);
    }

    #[Test]
    #[DataProvider('pageFitsProvider')]
    public function every_bubble_fits_on_the_page(float $x, float $y): void
    {
        // A bubble past the right edge prints on the next page, or gets clipped,
        // and the reader then measures a location with no ink on it.
        $this->assertLessThan(
            OmrLayout::PAGE_WIDTH_MM - OmrLayout::BUBBLE_DIAMETER_MM,
            $x,
            'A bubble centre is too close to the right edge'
        );
        $this->assertLessThan(
            OmrLayout::PAGE_HEIGHT_MM - OmrLayout::BUBBLE_DIAMETER_MM,
            $y,
            'A bubble centre is too close to the bottom edge'
        );
        $this->assertGreaterThan(0, $x);
        $this->assertGreaterThan(0, $y);
    }

    /** @return iterable<string, array{float, float}> */
    public static function pageFitsProvider(): iterable
    {
        $questions = collect(range(1, OmrLayout::QUESTIONS_PER_PAGE))
            ->map(fn (int $i) => self::staticQuestion($i));

        foreach (OmrLayout::forQuestions($questions) as $entry) {
            foreach ($entry['options'] as $letter => $point) {
                yield "row {$entry['row']} option {$letter}" => [$point['x'], $point['y']];
            }
        }
    }

    private static function staticQuestion(int $i): ExamQuestion
    {
        $question = new ExamQuestion;
        $question->id = $i;
        $question->number = $i;
        $question->kind = ExamQuestion::KIND_MULTIPLE_CHOICE;
        $question->options = ['options' => ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h']];

        return $question;
    }

    #[Test]
    public function the_widest_possible_grid_still_fits_the_page(): void
    {
        // Guarded separately from the data provider so that a change to COLUMNS
        // or COLUMN_PITCH_MM cannot quietly push the last column off the paper.
        $layout = OmrLayout::forQuestions(collect([self::staticQuestion(1)]));

        $rightmost = $layout[1]['options']['H'];

        $this->assertLessThan(
            OmrLayout::PAGE_WIDTH_MM,
            $rightmost['x'],
            'Eight columns no longer fit on A4'
        );
    }

    #[Test]
    public function the_header_height_and_the_first_row_coordinate_agree(): void
    {
        // These are two descriptions of the same distance and were wrong apart
        // once already: the header block was sized for three lines of text and
        // then silently clipped a fourth, which is the fixed-height approach
        // working as intended, but only because GRID_TOP_MM was moved with it.
        // Tied together here so moving one without the other fails here.
        $this->assertEqualsWithDelta(
            OmrLayout::HEADER_HEIGHT_MM,
            OmrLayout::printedDetailHeight(),
            0.001,
            'The header height and GRID_TOP_MM describe the same distance and must not drift'
        );
    }

    #[Test]
    public function the_first_row_leaves_room_for_its_own_label(): void
    {
        // The option letter is printed under the ring. If the row pitch were
        // tighter than the ring plus its label, a candidate reading the letters
        // would find the next row's ring sitting on top of this row's letter.
        $labelRoom = OmrLayout::ROW_PITCH_MM - OmrLayout::printedDiameter();

        $this->assertGreaterThan(
            2.0,
            $labelRoom,
            'Not enough room under a ring for its letter to be legible'
        );
    }

    #[Test]
    public function the_widest_grid_fits_within_the_printable_width(): void
    {
        // Guarded from the right-hand edge as well as the left, since the grid is
        // positioned from the left margin outwards and nothing else would notice
        // a column pitch that grew too wide to print.
        $rightmost = OmrLayout::GRID_LEFT_MM
            + ((OmrLayout::COLUMNS - 1) * OmrLayout::COLUMN_PITCH_MM)
            + (OmrLayout::printedDiameter() / 2);

        $this->assertLessThan(
            OmrLayout::PAGE_WIDTH_MM - 12.0,
            $rightmost,
            'The widest grid runs into the right margin'
        );
    }
}
