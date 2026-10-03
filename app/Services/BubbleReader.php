<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Reads pencil fill from the printed bubbles (OMR-02 to OMR-05).
 *
 * Takes a page image and the geometry from OmrLayout, and returns one reading per
 * question. It never guesses: an ambiguous sheet is reported as ambiguous so it
 * goes to a person, which is the same rule the AI marking path follows (BR-02).
 * A reader that resolved doubt by picking the darker option would be a silent
 * source of wrong marks, and objective marks that are simply wrong are harder to
 * catch than handwriting that looks wrong.
 *
 * The one thing that is easy to get wrong here is what counts as ink. A bubble is
 * printed as an outlined circle, so its ring is always dark. Sampling the whole
 * disc therefore returns a near constant ratio whether a candidate filled it or
 * not, and every sheet reads as blank or every sheet reads as full. Only the
 * interior, inside the ring, carries information about the candidate.
 *
 * This reader assumes the page image is already aligned to the sheet. Cropping to
 * the fiducials and correcting rotation is the assembler's job (IDN-09), and this
 * class is deliberately not allowed to invent its own alignment, because a second
 * opinion about where the page starts is exactly how the two would disagree.
 */
final class BubbleReader
{
    /** Below this fraction of the interior darkened, the bubble was left blank. */
    public const BLANK_THRESHOLD = 0.25;

    /** Above this fraction, the candidate filled the bubble. */
    public const FILLED_THRESHOLD = 0.55;

    public const ANSWER_BLANK = 'blank';

    public const ANSWER_MARKED = 'marked';

    public const ANSWER_AMBIGUOUS = 'ambiguous';

    /**
     * Read one page.
     *
     * @param  array<int, array{page:int,row:int,options:array<string,array{x:float,y:float}>}>  $layout
     * @return array<int, array{question_id:int,status:string,option:?string,confidence:?float}>
     */
    public function readPage(\GdImage $image, array $layout, int $pageNumber, float $dpi): array
    {
        // imagecolorat() answers with the palette INDEX rather than a colour on a
        // palette image. Decoding that index as if it were RGB turns white paper
        // into black, which makes every bubble on the sheet read as completely
        // filled. It is a quiet failure because nothing errors and every answer
        // comes back confidently wrong.
        //
        // Normalised here rather than at the call sites, because the same mistake
        // is easy to make twice and this class is the only thing that reads
        // pixels.
        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        $readings = [];

        foreach ($layout as $questionId => $entry) {
            if ($entry['page'] !== $pageNumber) {
                continue;
            }

            $readings[$questionId] = $this->readQuestion($image, (int) $questionId, $entry, $dpi);
        }

        return $readings;
    }

    /**
     * @param  array{page:int,row:int,options:array<string,array{x:float,y:float}>}  $entry
     * @return array{question_id:int,status:string,option:?string,confidence:?float}
     */
    private function readQuestion(\GdImage $image, int $questionId, array $entry, float $dpi): array
    {
        $filled = [];

        foreach ($entry['options'] as $letter => $point) {
            $ratio = $this->fillRatio(
                $image,
                $point['x'],
                $point['y'],
                $this->pixelsPerMillimetre($dpi)
            );

            if ($ratio >= self::FILLED_THRESHOLD) {
                $filled[$letter] = $ratio;
            } elseif ($ratio > self::BLANK_THRESHOLD) {
                // Ink that is neither clearly in nor clearly out. A pencil
                // dragged across the edge of a bubble lands here, and so does a
                // genuine answer that was only touched lightly. Reporting these
                // as ambiguous is the difference between sending one question to
                // a marker and quietly scoring it wrong.
                $filled[$letter] = $ratio;
                $filled[$letter . ':partial'] = true;
            }
        }

        return $this->decide($questionId, $filled);
    }

    /**
     * @param  array<string,float>  $filled  option letter to fill ratio, only above the blank threshold
     * @return array{question_id:int,status:string,option:?string,confidence:?float}
     */
    private function decide(int $questionId, array $filled): array
    {
        $marks = array_filter($filled, fn ($v, $k) => ! str_contains($k, ':'), ARRAY_FILTER_USE_BOTH);
        $partials = array_filter($filled, fn ($v) => $v === true, ARRAY_FILTER_USE_BOTH);

        $base = ['question_id' => $questionId, 'status' => self::ANSWER_BLANK, 'option' => null, 'confidence' => null];

        // Nothing at all above the blank threshold: genuinely unanswered, which
        // is a real answer and not an error.
        if ($marks === [] && $partials === []) {
            return $base;
        }

        // Two clear marks is a contradiction. It happens when a candidate changes
        // their mind without erasing, which is common and not worth guessing at.
        if (count($marks) > 1) {
            return ['question_id' => $questionId, 'status' => self::ANSWER_AMBIGUOUS, 'option' => null, 'confidence' => null];
        }

        // Ink in the grey band, or ink outside the chosen bubble, is not something
        // to resolve by picking the darkest.
        if ($partials !== []) {
            return ['question_id' => $questionId, 'status' => self::ANSWER_AMBIGUOUS, 'option' => null, 'confidence' => null];
        }

        $option = array_key_first($marks);
        $ratio = $marks[$option];

        // How far past the threshold the fill sits, as a rough margin. Useful when
        // a marker is deciding whether to trust the machine's suggestion, and
        // honest about being rough: it is not a probability.
        $span = self::FILLED_THRESHOLD - self::BLANK_THRESHOLD;
        $confidence = min(1.0, max(0.0, ($ratio - self::BLANK_THRESHOLD) / $span));

        return [
            'question_id' => $questionId,
            'status' => self::ANSWER_MARKED,
            'option' => $option,
            'confidence' => round($confidence, 3),
        ];
    }

    /**
     * How much of the bubble's interior is darkened.
     *
     * Samples inside 55% of the printed radius. The ring itself is ink on every
     * sheet and carries no information about the candidate.
     */
    public function fillRatio(\GdImage $image, float $centreXmm, float $centreYmm, float $scale): float
    {
        $centreX = (int) round($centreXmm * $scale);
        $centreY = (int) round($centreYmm * $scale);

        // Beyond the printed ring, so the interior is not polluted by it.
        $radius = max(1, (int) round((OmrLayout::BUBBLE_DIAMETER_MM / 2) * 0.55 * $scale));

        $width = imagesx($image);
        $height = imagesy($image);

        if ($centreX - $radius < 0 || $centreY - $radius < 0
            || $centreX + $radius >= $width || $centreY + $radius >= $height) {
            // Off the page means the geometry and the image disagree. Guessing
            // here would produce a confident reading of nothing.
            return 0.0;
        }

        $total = 0;
        $dark = 0;

        for ($y = $centreY - $radius; $y <= $centreY + $radius; $y++) {
            for ($x = $centreX - $radius; $x <= $centreX + $radius; $x++) {
                if (($x - $centreX) ** 2 + ($y - $centreY) ** 2 > $radius ** 2) {
                    continue;
                }

                $total++;
                $dark += $this->isDark($image, $x, $y) ? 1 : 0;
            }
        }

        return $total === 0 ? 0.0 : $dark / $total;
    }

    /**
     * Whether a pixel counts as ink.
     *
     * Luminance rather than any colour channel, because pencil on newsprint is
     * grey and a coloured pen is still a mark. The threshold is well below middle
     * grey so a light stroke still counts.
     */
    private function isDark(\GdImage $image, int $x, int $y): bool
    {
        $rgb = imagecolorat($image, $x, $y);

        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;

        // Rec. 601 luma, the same weighting the eye applies.
        $luma = (0.299 * $r) + (0.587 * $g) + (0.114 * $b);

        return $luma < 128;
    }

    private function pixelsPerMillimetre(float $dpi): float
    {
        return $dpi / 25.4;
    }
}
