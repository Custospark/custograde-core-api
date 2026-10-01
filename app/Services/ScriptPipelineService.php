<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\Script;
use App\Models\ScriptAnswer;
use App\Services\Contracts\AiServiceInterface;
use App\Services\Contracts\ScriptPipelineServiceInterface as ScriptPipelineServiceContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * The script pipeline: read the handwriting, then propose marks (CAP, OCR, AIG).
 *
 * Two invariants shape this service.
 *
 * First, nothing here writes a mark. Transcription fills `machine_text` and the
 * AI fills `suggested_mark`. The `mark` column is only ever written by a human
 * through MarkingService, which is what makes BR-02 structural rather than a
 * convention.
 *
 * Second, a failure never destroys work. A question the reader could not make
 * sense of becomes `non_text` and waits for a teacher. A question the reader
 * skipped gets no row at all and is reported. Neither is quietly scored zero,
 * because a wrong zero in a released result is the failure an examination board
 * will not accept.
 */
class ScriptPipelineService implements ScriptPipelineServiceContract
{
    public function __construct(
        private readonly AiServiceInterface $ai,
    ) {}

    /**
     * Read the scan and propose marks for one script.
     *
     * Returns a report rather than throwing on partial success, because a
     * teacher needs to know what did and did not come back even when part of it
     * failed. Only a total failure of the AI service is reported as an error.
     *
     * @return array<string, mixed>
     */
    public function process(Script $script): array
    {
        $exam = $script->exam()->firstOrFail();
        $questions = $exam->questions()->orderBy('number')->get();

        $script->update(['status' => Script::STATUS_TRANSCRIBING]);

        // --- Read the handwriting (OCR-01) --------------------------------
        $transcription = $this->transcribe($script, $exam, $questions);

        // --- Propose marks (AIG-01) ---------------------------------------
        $grading = $this->proposeMarks($script, $exam, $questions);

        $script->refresh();

        return [
            'script_id' => $script->id,
            'transcribed' => $transcription['read'],
            'transcription_warnings' => $transcription['warnings'],
            'proposed' => $grading['proposed'],
            'grading_failures' => $grading['failures'],
            'warnings' => array_merge($transcription['warnings'], $grading['warnings']),
            'status' => $script->status,
            'cost_usd' => $transcription['cost'] + $grading['cost'],
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ExamQuestion>  $questions
     * @return array{read: int, warnings: list<string>, cost: float}
     */
    private function transcribe(Script $script, Exam $exam, $questions): array
    {
        $disk = Storage::disk($script->original_disk);

        if (! $disk->exists($script->original_path)) {
            throw new AiServiceException(
                'The uploaded scan is no longer on the server, so it cannot be read. Please upload it again.',
                retryable: false,
            );
        }

        $bytes = $disk->get($script->original_path);
        $mime = $script->mime_type ?: 'image/png';

        $questionTexts = [];
        foreach ($questions as $question) {
            $questionTexts[(string) $question->number] = (string) $question->prompt;
        }

        try {
            $result = $this->ai->transcribePage([
                'exam_id' => $exam->id,
                'script_id' => $script->id,
                'page_number' => 1,
                'image_base64' => base64_encode($bytes),
                'mime_type' => $mime,
                'language' => 'en',
                'expected_questions' => $questions->pluck('number')->map(fn ($n) => (int) $n)->all(),
                'question_texts' => $questionTexts,
            ]);
        } catch (AiServiceException $exception) {
            Log::warning('Transcription failed for script', [
                'script_id' => $script->id,
                'retryable' => $exception->retryable,
            ]);

            // A total transcription failure leaves the script untouched and
            // markable by hand, which is what AIG-11 requires.
            if (! $exception->retryable) {
                $script->update(['status' => Script::STATUS_UPLOADED]);
            }

            throw $exception;
        }

        $cost = $this->sumCost($result['usage'] ?? []);

        $byNumber = [];
        foreach ($result['answers'] as $answer) {
            $byNumber[(int) ($answer['question_number'] ?? 0)] = $answer;
        }

        $read = 0;

        DB::transaction(function () use ($script, $questions, $byNumber, $result): void {
            foreach ($questions as $question) {
                $number = (int) $question->number;
                $reading = $byNumber[$number] ?? null;

                $answer = ScriptAnswer::query()->firstOrNew([
                    'script_id' => $script->id,
                    'question_number' => $number,
                ]);

                $answer->question_id = $question->id;
                $answer->transcription_model = (string) ($result['model_version'] ?? '');

                if ($reading === null) {
                    // The reader did not report this question. It stays pending
                    // with no transcription, so a teacher marks it by hand. It
                    // is never scored zero for being unread.
                    $answer->content_type = ScriptAnswer::CONTENT_PENDING;
                    $answer->machine_text = null;
                } else {
                    $contentType = (string) ($reading['content_type'] ?? 'text');

                    $answer->content_type = in_array($contentType, ScriptAnswer::CONTENT_TYPES, true)
                        ? $contentType
                        : ScriptAnswer::CONTENT_NON_TEXT;

                    $answer->machine_text = $answer->content_type === ScriptAnswer::CONTENT_BLANK
                        ? null
                        : ($reading['text'] ?? null);

                    $answer->transcription_confidence = $reading['confidence'] ?? null;
                    $answer->truncated = (bool) ($reading['truncated'] ?? false);
                    $answer->transcription_note = $reading['note'] ?? null;

                    // Stored as JSON because the column is text. A plain comma
                    // separated string here would fail to decode.
                    $words = $reading['low_confidence_words'] ?? [];
                    $answer->low_confidence_words = is_array($words) && $words !== []
                        ? json_encode(array_values($words))
                        : null;

                    if ($answer->content_type === ScriptAnswer::CONTENT_TEXT) {
                        $read++;
                    }
                }

                $answer->save();
            }
        });

        return [
            'read' => $read,
            'warnings' => $result['warnings'] ?? [],
            'cost' => $cost,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ExamQuestion>  $questions
     * @return array{proposed: int, failures: list<array<string, mixed>>, warnings: list<string>, cost: float}
     */
    private function proposeMarks(Script $script, Exam $exam, $questions): array
    {
        $answers = ScriptAnswer::query()
            ->where('script_id', $script->id)
            ->orderBy('question_number')
            ->get()
            ->keyBy('question_number');

        $payloadQuestions = [];
        $byNumber = [];

        foreach ($questions as $question) {
            $answer = $answers->get((int) $question->number);
            if ($answer === null) {
                continue;
            }

            // A graph, a blank or an unread answer cannot be graded from text.
            // OCR-07 routes these to a human rather than asking the model to
            // guess, which is what produced the "refuse to grade a diagram"
            // behaviour in testing.
            if ($answer->content_type !== ScriptAnswer::CONTENT_TEXT) {
                continue;
            }

            if (trim((string) $answer->machine_text) === '') {
                continue;
            }

            $guidePoints = [];
            foreach ((array) $question->guide_points as $point) {
                if (is_array($point) && isset($point['label'])) {
                    $guidePoints[] = [
                        'label' => (string) $point['label'],
                        'marks' => (float) ($point['marks'] ?? 0),
                        'keywords' => array_values(array_map('strval', (array) ($point['keywords'] ?? []))),
                    ];
                }
            }

            // A question with no guide points cannot be graded against a guide,
            // so it is left for the teacher rather than graded on generalities.
            if ($guidePoints === []) {
                continue;
            }

            $lowWords = $answer->low_confidence_words;
            if (is_string($lowWords) && $lowWords !== '') {
                $decoded = json_decode($lowWords, true);
                $lowWords = is_array($decoded) ? $decoded : [];
            }

            $payloadQuestions[] = [
                'question_number' => (int) $question->number,
                'question_text' => (string) $question->prompt,
                'max_mark' => (float) $question->max_mark,
                'granularity' => (float) $question->granularity,
                'guide_points' => $guidePoints,
                'answer_text' => (string) $answer->machine_text,
                'low_confidence_words' => is_array($lowWords) ? array_values($lowWords) : [],
                'strategy' => 'semantic',
            ];
            $byNumber[(int) $question->number] = $question;
        }

        if ($payloadQuestions === []) {
            // Nothing readable on this paper. That is a valid state: the
            // teacher marks it by hand, and the script says so.
            $script->update(['status' => Script::STATUS_IN_REVIEW]);

            return ['proposed' => 0, 'failures' => [], 'warnings' => [
                'Nothing on this script could be read as handwriting, so it needs marking by hand.',
            ], 'cost' => 0.0];
        }

        $result = $this->ai->suggestMarks([
            'exam_id' => $exam->id,
            'subject' => $exam->courseUnit?->title,
            'marking_scheme_notes' => null,
            'questions' => $payloadQuestions,
        ]);

        $cost = $this->sumCost($result['usage'] ?? []);
        $proposed = 0;

        DB::transaction(function () use ($script, $byNumber, $result, &$proposed): void {
            foreach ($result['proposals'] as $proposal) {
                $number = (int) ($proposal['question_number'] ?? 0);
                if (! isset($byNumber[$number])) {
                    continue;
                }

                $answer = ScriptAnswer::query()
                    ->where('script_id', $script->id)
                    ->where('question_number', $number)
                    ->first();

                if ($answer === null) {
                    continue;
                }

                // A mark already approved by a teacher is never overwritten by a
                // later suggestion. EXM-07 requires a guide change to offer
                // re-evaluation rather than silently replacing results.
                if ($answer->isDecided()) {
                    continue;
                }

                $answer->suggested_mark = $proposal['suggested_mark'] ?? null;
                $answer->suggestion_confidence = $proposal['confidence'] ?? null;
                $answer->suggestion_rationale = $proposal['rationale'] ?? null;
                $answer->suggestion_strategy = $proposal['strategy_used'] ?? null;
                $answer->suggestion_model = (string) ($proposal['model_version'] ?? '');
                $answer->matched_points = $proposal['points'] ?? null;
                $answer->mark_source = ScriptAnswer::SOURCE_AI_SUGGESTED;
                $answer->suggested_at = now();
                $answer->save();

                $proposed++;
            }
        });

        $warnings = [];

        foreach ($result['failures'] as $failure) {
            $numbers = $failure['questions'] ?? [];
            $label = $numbers === [] ? 'some questions' : 'question(s) ' . implode(', ', $numbers);
            $warnings[] = sprintf(
                'The AI could not mark %s: %s It has been left for a teacher.',
                $label,
                (string) ($failure['error'] ?? 'unknown reason')
            );
        }

        // Status is derived from what actually got decided, so it can never
        // disagree with the answers on the paper.
        $script->update([
            'status' => $script->isComplete()
                ? Script::STATUS_READY_TO_LOCK
                : Script::STATUS_SUGGESTED,
        ]);

        return [
            'proposed' => $proposed,
            'failures' => $result['failures'] ?? [],
            'warnings' => $warnings,
            'cost' => $cost,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $usage
     */
    private function sumCost(array $usage): float
    {
        $total = 0.0;

        foreach ($usage as $record) {
            $total += (float) ($record['cost_usd'] ?? 0);
        }

        return round($total, 6);
    }
}
