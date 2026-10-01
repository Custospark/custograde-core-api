<?php

namespace App\Services;

use App\Services\Contracts\AiServiceInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP client for the Python AI service.
 *
 * The provider key never reaches Laravel. The AI service reads its own
 * credentials, so a database dump of a customer tenant cannot leak a model key,
 * and the browser cannot call a model directly at all.
 */
class AiService implements AiServiceInterface
{
    public function __construct(
        private readonly int $timeout = 180,
        private readonly int $connectTimeout = 10,
    ) {}

    /**
     * The AI service base URL and the shared secret it expects.
     *
     * Read from config so a deployment can point at another host, and so the
     * absence of a secret is a visible configuration state rather than a
     * silent open door.
     *
     * @return array{base: string, key: string}
     */
    private function connection(): array
    {
        return [
            'base' => rtrim((string) config('ai.service_url', 'http://127.0.0.1:8100'), '/'),
            'key' => (string) config('ai.internal_key', ''),
        ];
    }

    public function transcribePage(array $payload): array
    {
        $response = $this->post('/v1/ocr/transcribe', $payload);

        return [
            'answers' => $response['answers'] ?? [],
            'warnings' => $response['warnings'] ?? [],
            'model_version' => (string) ($response['model_version'] ?? 'unknown'),
            'usage' => $response['usage'] ?? [],
        ];
    }

    public function suggestMarks(array $payload): array
    {
        $response = $this->post('/v1/grade/suggest', $payload);

        return [
            'proposals' => $response['proposals'] ?? [],
            'failures' => $response['failures'] ?? [],
            'usage' => $response['usage'] ?? [],
        ];
    }

    public function health(): array
    {
        $connection = $this->connection();

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->acceptJson()
                ->get($connection['base'] . '/health');
        } catch (ConnectionException) {
            return [
                'reachable' => false,
                'provider_configured' => false,
                'message' => 'The AI service could not be reached. Marking by hand still works.',
            ];
        }

        if (! $response->successful()) {
            return [
                'reachable' => false,
                'provider_configured' => false,
                'message' => 'The AI service did not answer a health check.',
            ];
        }

        $body = $response->json() ?? [];

        return [
            'reachable' => true,
            'provider_configured' => (bool) ($body['provider_configured'] ?? false),
            'model' => $body['model'] ?? null,
            'vision_model' => $body['vision_model'] ?? null,
            'version' => $body['version'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        $connection = $this->connection();

        $request = Http::timeout($this->timeout)
            ->connectTimeout($this->connectTimeout)
            ->acceptJson()
            ->asJson();

        // The shared secret is only sent when one is configured, matching the AI
        // service which skips the check when none is set. That combination is a
        // developer-machine state and is reported by the health endpoint.
        if ($connection['key'] !== '') {
            $request = $request->withHeaders(['X-Internal-Key' => $connection['key']]);
        }

        try {
            $response = $request->post($connection['base'] . $path, $payload);
        } catch (ConnectionException $exception) {
            Log::warning('AI service unreachable', ['path' => $path]);

            throw new AiServiceException(
                'The AI service could not be reached, so this script has not been read yet. '
                . 'You can mark it by hand in the meantime.',
                retryable: true,
            );
        }

        if ($response->successful()) {
            return $response->json() ?? [];
        }

        $status = $response->status();
        $detail = $this->errorDetail($response->json());

        // 503 from the AI service means it ran out of budget or was rate
        // limited, both of which succeed on a later attempt.
        if ($status === 503) {
            Log::warning('AI service busy', ['path' => $path, 'status' => $status]);

            throw new AiServiceException(
                $detail ?: 'The AI service is busy. This will be retried automatically.',
                retryable: true,
            );
        }

        if ($status === 422) {
            // A scan the service refuses, such as an unreadable image. Retrying
            // cannot help, so this must reach a human rather than the queue.
            Log::warning('AI service rejected the payload', ['path' => $path, 'status' => $status]);

            throw new AiServiceException(
                $detail ?: 'This scan could not be read. Please check the file and upload it again.',
                retryable: false,
            );
        }

        Log::error('AI service error', ['path' => $path, 'status' => $status]);

        throw new AiServiceException(
            $detail ?: 'The AI service had a problem. This will be retried automatically.',
            retryable: true,
        );
    }

    /**
     * Pull a readable message out of whatever shape the error arrived in.
     *
     * @param  array<string, mixed>|null  $body
     */
    private function errorDetail(?array $body): ?string
    {
        $detail = $body['detail'] ?? null;

        if (is_array($detail) && isset($detail['error']) && is_string($detail['error'])) {
            return $detail['error'];
        }

        if (is_string($detail) && $detail !== '') {
            return $detail;
        }

        if (isset($body['message']) && is_string($body['message'])) {
            return $body['message'];
        }

        return null;
    }
}
