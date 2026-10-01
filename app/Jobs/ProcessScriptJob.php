<?php

namespace App\Jobs;

use App\Models\Script;
use App\Services\AiServiceException;
use App\Services\Contracts\AiServiceInterface;
use App\Services\ScriptPipelineService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Reads a captured script and proposes marks, in the background.
 *
 * Queued because the measured cost of one script is 25 to 35 seconds: reading the
 * page is 13, grading the answers is 10 to 25. Holding a web request open for
 * that would exhaust PHP workers long before it exhausted a marking session.
 *
 * A retryable failure goes back on the queue. A non-retryable one, such as a scan
 * the service refuses, stops retrying and leaves the script markable by hand,
 * because a queue that retries a malformed image forever is a queue that never
 * drains (CAP-09).
 */
class ProcessScriptJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Back off between attempts so a rate limit is not hammered. */
    public array $backoff = [30, 120, 300];

    public int $timeout = 600;

    public function __construct(public readonly int $scriptId) {}

    public function handle(ScriptPipelineService $pipeline, AiServiceInterface $ai): void
    {
        $script = Script::query()->find($this->scriptId);

        if ($script === null) {
            Log::info('Script no longer exists, nothing to process', ['script_id' => $this->scriptId]);

            return;
        }

        if ($script->isLocked()) {
            Log::info('Script is already locked, skipping', ['script_id' => $script->id]);

            return;
        }

        try {
            $report = $pipeline->process($script);
        } catch (AiServiceException $exception) {
            Log::warning('Script processing failed', [
                'script_id' => $script->id,
                'retryable' => $exception->retryable,
                'attempts' => $this->attempts(),
            ]);

            if (! $exception->retryable) {
                // Put it back to uploaded so a teacher sees it as an ordinary
                // unprocessed paper rather than a stuck one.
                $script->update(['status' => Script::STATUS_UPLOADED]);
                $this->fail($exception);

                return;
            }

            throw $exception;
        }

        Log::info('Script processed', [
            'script_id' => $script->id,
            'transcribed' => $report['transcribed'],
            'proposed' => $report['proposed'],
            'status' => $report['status'],
            'cost_usd' => $report['cost_usd'],
        ]);
    }

    /**
     * Give up after the final attempt without throwing, so the script is left in
     * a state a teacher can act on rather than vanishing into failed_jobs.
     */
    public function failed(?\Throwable $exception): void
    {
        Script::query()
            ->whereKey($this->scriptId)
            ->where('status', '!=', Script::STATUS_LOCKED)
            ->update(['status' => Script::STATUS_UPLOADED]);

        Log::error('Script processing abandoned, left for a teacher', [
            'script_id' => $this->scriptId,
            'reason' => $exception?->getMessage(),
        ]);
    }
}
