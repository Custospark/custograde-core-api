<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\Script;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * What the candidate filled in on the objective section (OMR-02 to OMR-06).
 *
 * Reads the stored scan and answers with the reading, not the mark. Nothing here
 * decides a mark: the reading is a suggestion of the same kind as the AI's
 * transcription, and a marker confirms it like any other (BR-02). An objective
 * answer that is confidently wrong is more dangerous than a wrong essay, because
 * nobody re-reads forty bubbles by eye to check the machine.
 *
 * The readings are cached against the scan's own hash together with a fingerprint
 * of the layout they were read against. That pair is what makes the cache safe
 * rather than merely convenient: the scan is immutable once captured, and if the
 * sheet template or the question options ever change, the fingerprint changes
 * with them and the stale readings are never served.
 */
final class OmrReadingService
{
    /**
     * Scans arrive at whatever resolution the office scanner produced, so the
     * reader is told rather than left to guess. 300dpi is the floor this was
     * measured at, and below it the bubble interiors get too few pixels to
     * separate a shaded bubble from a smudge.
     */
    public const DEFAULT_DPI = 300;

    /**
     * Readings for one script, keyed by question id.
     *
     * @return array<int, array{question_id:int,status:string,option:?string,confidence:?float}>
     */
    public function forScript(Script $script): array
    {
        $questions = $this->bubbledQuestions($script->exam);

        if ($questions->isEmpty() || $script->original_path === null) {
            return [];
        }

        $layout = OmrLayout::forQuestions($questions);
        $key = $this->cacheKey($script, $layout);

        $cached = cache()->get($key);

        if (is_array($cached)) {
            return $cached;
        }

        $readings = $this->read($script, $layout);

        // A scan never changes, so this is safe to keep for the life of the
        // script. If the sheet is reissued the code changes and so does the hash.
        cache()->forever($key, $readings);

        return $readings;
    }

    /**
     * The objective questions, in the order they were printed.
     */
    private function bubbledQuestions(?Exam $exam)
    {
        if ($exam === null) {
            return collect();
        }

        return OmrLayout::bubbled(
            ExamQuestion::query()->where('exam_id', $exam->id)->get()
        );
    }

    /**
     * @param  array<int, array{page:int,row:int,options:array<string,array{x:float,y:float}>}>  $layout
     * @return array<int, array{question_id:int,status:string,option:?string,confidence:?float}>
     */
    private function read(Script $script, array $layout): array
    {
        try {
            $path = Storage::disk($script->original_disk)->path($script->original_path);
            $image = $this->loadImage($path, (string) $script->mime_type);

            if ($image === null) {
                return [];
            }

            $readings = [];

            // Page count is honoured rather than assumed. Until assembly (IDN-01)
            // a scan is a single image, so only page one can be read. Reading past
            // it would report a question as blank because its page was never
            // looked at, which is the same as saying the candidate left it empty.
            $pages = max(1, (int) $script->page_count);

            for ($page = 1; $page <= $pages; $page++) {
                $readings += app(BubbleReader::class)->readPage(
                    $image,
                    $layout,
                    $page,
                    static::DEFAULT_DPI
                );
            }

            return $readings;
        } catch (\Throwable $exception) {
            // A scan this service cannot read is not a broken examination. The
            // marking screen shows the handwriting and the marker fills the
            // objective section in by hand, which is the fallback the design
            // already promises (AIG-11).
            Log::warning('OMR reading failed, falling back to manual marking', [
                'script_id' => $script->id,
                'reason' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    private function loadImage(string $path, string $mime): ?\GdImage
    {
        $image = match (strtolower($mime)) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => null,
        };

        if ($image === false) {
            return null;
        }

        // BubbleReader normalises this too, but only inside readPage. Doing it
        // here means a palette scan is handled before anything else measures it.
        if ($image !== null && ! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        return $image;
    }

    /**
     * A cache key that changes when either the scan or the sheet layout changes.
     *
     * @param  array<int, mixed>  $layout
     */
    private function cacheKey(Script $script, array $layout): string
    {
        $fingerprint = md5(json_encode($layout) ?: '');

        return sprintf('omr:%s:%s', $script->original_hash ?: $script->code, $fingerprint);
    }
}
