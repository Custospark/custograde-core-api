<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\Script;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Collection;

/**
 * Renders the printable answer sheet (SHT-01, SHT-03, SHT-04, SHT-08).
 *
 * The sheet exists to make three later steps possible, and every layout choice
 * serves one of them:
 *
 *  - A QR on **every** page, carrying that page's number, so a bundle that comes
 *    apart in a scanner tray can be re-associated instead of guessed (SHT-03).
 *  - Fiducial marks in all four corners, so page geometry can be normalised
 *    whatever angle the paper went in at.
 *  - One bordered region per question with its number in the same fixed grid,
 *    so a transcription request knows where to look instead of handing a vision
 *    model a whole page and hoping.
 *
 * SHT-08 asks for legibility in monochrome on A4, which rules out pale grey
 * rules and light fills. Everything printed here is either black or an explicit
 * tint chosen to survive a photocopier, and every label is set large, because a
 * candidate's handwriting has to fit inside the box and a cramped box costs marks.
 */
class AnswerSheetService
{
    public function __construct(private readonly SheetCodeService $codes) {}

    /**
     * Build the sheet for one script. A4 portrait, one page per question block.
     */
    public function render(Script $script): string
    {
        $script->loadMissing(['exam.courseUnit', 'exam.questions', 'student']);
        $exam = $script->exam;
        $questions = $this->questionsInOrder($exam);

        // Objective questions are bubbled and live on their own pages, after the
        // written ones (OmrLayout). Splitting them here as well as there is
        // deliberate: the layout decides that a bubble's position must not depend
        // on how much the previous candidate wrote, and that is only true if the
        // renderer honours it rather than laying the two kinds of question out in
        // one flow.
        $bubbled = OmrLayout::bubbled($questions);
        $written = $questions->reject(fn (ExamQuestion $q) => OmrLayout::isBubbled($q))->values();

        // A sheet is paginated by room for writing, not by question count. Five
        // questions per page keeps each region roughly 35mm tall, which is what
        // a legible multi-line answer actually needs.
        //
        // Zero pages when there is nothing to write, rather than one blank page.
        // An exam that is entirely multiple choice should not hand a candidate a
        // sheet with their name on it and no question on it, and a page count that
        // claims otherwise is also what the QR encodes.
        $pageCount = (int) ceil($written->count() / static::QUESTIONS_PER_PAGE);
        $script->forceFill([
            'expected_page_count' => $pageCount + OmrLayout::pageCount($questions),
        ])->saveQuietly();

        $pdf = Pdf::loadView('sheets.answer-sheet', [
            'script' => $script,
            'exam' => $exam,
            'student' => $script->student,
            'questions' => $written,
            'bubbled' => $bubbled,
            'omr' => OmrLayout::forQuestions($questions),
            'pageCount' => $pageCount,
            'questionsPerPage' => static::QUESTIONS_PER_PAGE,
            'qr' => fn (int $page) => $this->qrDataUri($this->codes->encode($script, $page, $pageCount)),
            'readable' => $this->codes->humanReadable($script, 1, $pageCount),
        ]);

        $pdf->setPaper('a4', 'portrait');

        // Rendering sheets is a bulk job for a whole class, so the output is
        // embedded rather than written to a temporary path nobody will clean up.
        return $pdf->output();
    }

    /** Questions per page. Fixed here and reused by the blade template. */
    public const QUESTIONS_PER_PAGE = 5;

    /**
     * A QR as an inline SVG data URI.
     *
     * SVG rather than a raster PNG because it stays sharp at any printer
     * resolution, which matters when the code has to survive being photocopied
     * (SHT-08). Error correction is raised so a smudged or creased code still
     * decodes, since the fallback for a failed decode is a person typing a code
     * by hand for thirty candidates.
     */
    private function qrDataUri(string $payload): string
    {
        $options = new QROptions;
        $options->outputType = QROutputInterface::MARKUP_SVG;
        $options->eccLevel = EccLevel::H;
        $options->svgUseFillAttributes = true;
        $options->svgFillAttributes = ['fill' => '#000000'];

        return (new QRCode($options))->render($payload);
    }

    /**
     * @return Collection<int, ExamQuestion>
     */
    private function questionsInOrder(Exam $exam): Collection
    {
        return ExamQuestion::query()
            ->where('exam_id', $exam->id)
            ->orderBy('number')
            ->get();
    }

    /**
     * The file name a candidate downloads. Carries the code rather than the
     * name, so a downloaded file sitting in a shared Downloads folder does not
     * disclose who sat the paper.
     */
    public function fileNameFor(Script $script): string
    {
        return sprintf('answer-sheet-%s.pdf', $script->code);
    }
}