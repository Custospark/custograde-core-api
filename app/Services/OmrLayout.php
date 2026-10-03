<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExamQuestion;
use Illuminate\Support\Collection;

/**
 * Where the bubbles are, in millimetres from the top left of the page (OMR-01).
 *
 * This class exists so the sheet and the reader cannot disagree. It is the single
 * source of truth for bubble geometry: the renderer draws at exactly these
 * coordinates, and the reader measures exactly these coordinates. If the two ever
 * derived their own numbers the failure would be silent and would show up as
 * objective answers scored at a systematic offset, which is the hardest kind of
 * marking error to notice.
 *
 * Two decisions worth keeping.
 *
 * Objective questions get their own pages, after the written ones. This is not
 * only because that is how OMR papers are conventionally laid out. Written answers
 * have variable height, so a bubble's vertical position inside a mixed page
 * depends on how much the previous candidate wrote. Putting objective questions
 * on their own pages makes the geometry pure arithmetic from the page number,
 * with no dependence on content at all.
 *
 * Geometry is absolute rather than flowed. The template positions bubbles at the
 * millimetre coordinates produced here instead of laying rows out in normal flow.
 * Flowed layout would be easier to write and would drift the moment a font metric
 * changed, so the numbers above would become a description of the page rather than
 * a promise about it.
 *
 * Dimensions are chosen for a school, not a laboratory. The bubble is 4.2mm
 * because a 2B pencil on newsprint makes a mark roughly 2mm across and the target
 * has to be comfortably larger than the tool. Row pitch is 9mm because a number
 * plus a letter has to be legible beside it at the size a teacher reads it, and
 * because a thumb smudged across one row must not reach the next.
 */
final class OmrLayout
{
    /**
     * The page margins, which must match the @page rule in the sheet template.
     *
     * Held here so that the reader and the renderer cannot disagree about where
     * the content box starts. The reader works in absolute millimetres from the
     * top left of the physical sheet because that is what a scan gives it, while
     * the template lays the grid out inside the content box. If the two derived
     * that offset separately they would drift the first time a margin changed,
     * and every objective answer would shift by the difference.
     */
    public const CONTENT_LEFT_MM = 12.0;

    public const CONTENT_TOP_MM = 14.0;

    /** A4 portrait, which is what the sheet renders. */
    public const PAGE_WIDTH_MM = 210.0;

    public const PAGE_HEIGHT_MM = 297.0;

    /**
     * Bubble diameter. Comfortably larger than a pencil mark so a candidate who
     * fills loosely still lands ink inside the target.
     */
    public const BUBBLE_DIAMETER_MM = 4.2;

    /**
     * Distance between row centres. Large enough that the printed option letter
     * sits outside the bubble a candidate might smudge into.
     */
    public const ROW_PITCH_MM = 9.0;

    /** Rows of questions on one objective page. */
    public const QUESTIONS_PER_PAGE = 12;

    /**
     * Options per row, A through H. Eight is the widest set most schools use,
     * and every extra column narrows the gap between the outer bubbles and the
     * page edge, where a copier tends to eat the mark.
     */
    public const COLUMNS = 8;

    /** Left edge of the bubble block. The number column sits to its left. */
    public const GRID_LEFT_MM = 30.0;

    /**
     * Top of the first row's centre, measured from the top of the physical sheet.
     *
     * This is the reader's coordinate, so it must be the true centre of the
     * printed ring rather than the top of a box.
     */
    public const GRID_TOP_MM = 38.45;

    /**
     * Stroke width of the printed ring.
     *
     * Held as a constant because it is part of what gets printed. dompdf does not
     * apply border-box sizing to these elements, so the border sits outside the
     * declared width and the ring is genuinely wider than BUBBLE_DIAMETER_MM.
     */
    public const BUBBLE_BORDER_MM = 0.35;

    /**
     * Height of the fixed block that sits above the grid.
     *
     * Fixed on purpose. The grid is a table positioned by normal flow, so where
     * it starts depends on the height of whatever precedes it, and a header whose
     * height depends on how long a candidate's name is would move every bubble on
     * the page by however much it differed. The block is given a height and
     * clipped instead, and printedDetailHeight() ties it to GRID_TOP_MM so the
     * two cannot drift apart.
     */
    public const HEADER_HEIGHT_MM = 22.0;

    /**
     * The distance between option centres.
     */
    public const COLUMN_PITCH_MM = 11.0;

    /**
     * Diameter of the ring as it actually comes off the printer.
     */
    public static function printedDiameter(): float
    {
        return self::BUBBLE_DIAMETER_MM + (2 * self::BUBBLE_BORDER_MM);
    }

    /**
     * The height the block above the grid must occupy for GRID_TOP_MM to be true.
     *
     * A table cell holds the ring at its top, so the ring's centre sits half a
     * printed diameter below the cell's top edge.
     */
    public static function printedDetailHeight(): float
    {
        return self::GRID_TOP_MM - self::CONTENT_TOP_MM - (self::printedDiameter() / 2);
    }

    /**
     * Width of the blank cell before the first option.
     *
     * The table starts at the left page margin and the first bubble's centre has
     * to land on GRID_LEFT_MM, so the grid is indented by this much.
     */
    public static function gridIndent(): float
    {
        return self::GRID_LEFT_MM - self::CONTENT_LEFT_MM - (self::COLUMN_PITCH_MM / 2);
    }

    /**
     * Convert an absolute page coordinate to an offset inside the content box,
     * which is what CSS positioning inside @page margins is relative to.
     *
     * @return array{left:float,top:float}
     */
    public static function toContentOffset(float $pageXmm, float $pageYmm): array
    {
        return [
            'left' => $pageXmm - self::CONTENT_LEFT_MM,
            'top' => $pageYmm - self::CONTENT_TOP_MM,
        ];
    }

    /**
     * Only multiple choice is bubbled.
     *
     * Numeric is in the model's objective list but is not a selection: a candidate
     * writes a number, and there is no bubble to darken. Treating it as bubbled
     * would print a grid nothing is ever read from and quietly score every
     * candidate wrong, which is the worst possible failure for this feature.
     */
    public static function isBubbled(ExamQuestion $question): bool
    {
        return $question->kind === ExamQuestion::KIND_MULTIPLE_CHOICE;
    }

    /**
     * The bubbled questions, in the order they will be printed.
     */
    public static function bubbled(Collection $questions): Collection
    {
        return $questions
            ->filter(fn (ExamQuestion $q) => self::isBubbled($q))
            ->sortBy('number')
            ->values();
    }

    /**
     * Bubble geometry for every bubbled question.
     *
     * Returned as a list keyed by question id, each entry holding the page the
     * question lands on and its options with centre coordinates in millimetres.
     * The reader takes this and nothing else.
     *
     * @return array<int, array{page:int,row:int,options:array<string,array{x:float,y:float}>}>
     */
    public static function forQuestions(Collection $questions): array
    {
        $bubbled = self::bubbled($questions);

        if ($bubbled->isEmpty()) {
            return [];
        }

        $layout = [];

        foreach ($bubbled->values() as $index => $question) {
            $page = intdiv($index, self::QUESTIONS_PER_PAGE) + 1;
            $row = $index % self::QUESTIONS_PER_PAGE;
            $top = self::GRID_TOP_MM + ($row * self::ROW_PITCH_MM);

            $options = [];
            foreach (self::optionLetters($question) as $letter) {
                $column = array_search($letter, self::letters(), true);
                $options[$letter] = [
                    'x' => self::GRID_LEFT_MM + ($column * self::COLUMN_PITCH_MM),
                    'y' => $top,
                ];
            }

            $layout[(int) $question->id] = [
                'page' => $page,
                'row' => $row,
                'options' => $options,
            ];
        }

        return $layout;
    }

    /**
     * Option letters a question actually offers.
     *
     * Taken from the question's own options rather than assumed to be four. A
     * three option question must print three bubbles: a phantom fourth column is
     * a trap a candidate can fill, and the reader would then have to decide
     * whether that mark was a real answer or a smudge.
     */
    public static function optionLetters(ExamQuestion $question): array
    {
        $options = is_array($question->options) ? $question->options : [];

        $count = match (true) {
            array_key_exists('options', $options) && is_array($options['options']) => count($options['options']),
            is_array($options) => count($options),
            default => 0,
        };

        return array_slice(self::letters(), 0, max(0, min($count, self::COLUMNS)));
    }

    /** @return list<string> */
    public static function letters(int $upTo = self::COLUMNS): array
    {
        $letters = [];

        for ($i = 0; $i < $upTo; $i++) {
            $letters[] =chr(ord('A') + $i);
        }

        return $letters;
    }

    /** How many objective pages a set of questions needs. */
    public static function pageCount(Collection $questions): int
    {
        $count = self::bubbled($questions)->count();

        return $count === 0 ? 0 : (int) ceil($count / self::QUESTIONS_PER_PAGE);
    }
}
