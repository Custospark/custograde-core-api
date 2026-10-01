<?php

namespace App\Services\Contracts;

/**
 * The bridge to the Python AI service (CON-03).
 *
 * The AI service is the only component that talks to a model. Laravel never
 * holds a provider key, and the browser never reaches either service directly,
 * which is what AIG-10 requires: only the data needed for grading, and only
 * through a backend proxy.
 *
 * Every method returns a plain array or throws, so a caller never has to know
 * whether a transcription came from a vision model or, later, a dedicated OCR
 * engine (OCR-01). That swap is the reason this is an interface.
 */
interface AiServiceInterface
{
    /**
     * Read handwriting off one scanned page (OCR-01).
     *
     * @param  array<string, mixed>  $payload  exam_id, script_id, page_number, image_base64, mime_type, expected_questions, question_texts
     * @return array{answers: list<array<string, mixed>>, warnings: list<string>, model_version: string, usage: list<array<string, mixed>>}
     *
     * @throws AiServiceException
     */
    public function transcribePage(array $payload): array;

    /**
     * Propose marks for short answers against their marking guides (AIG-01).
     *
     * @param  array<string, mixed>  $payload  exam_id, subject, marking_scheme_notes, questions[]
     * @return array{proposals: list<array<string, mixed>>, failures: list<array<string, mixed>>, usage: list<array<string, mixed>>}
     *
     * @throws AiServiceException
     */
    public function suggestMarks(array $payload): array;

    /**
     * Liveness plus whether the provider is configured.
     *
     * The dashboard needs to be able to say "the AI is not configured" plainly,
     * rather than letting a teacher discover it halfway through a paper.
     *
     * @return array<string, mixed>
     */
    public function health(): array;
}
