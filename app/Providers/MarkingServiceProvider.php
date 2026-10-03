<?php

namespace App\Providers;

use App\Services\AiService;
use App\Services\Contracts\AiServiceInterface;
use App\Services\Contracts\ScriptPipelineServiceInterface;
use App\Services\ScriptPipelineService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Named rate limiters for the marking chain.
 *
 * These are deliberately named rather than using the inline `throttle:10,1`
 * form, because every inline limiter for one user shares a single counter. A
 * teacher marking a 300 script paper uploads 300 scans and approves 300
 * scripts, so with a shared counter they would throttle themselves out of the
 * very actions the limits exist to protect. Each allowance here is per action.
 *
 * The mark limiter is generous on purpose: a marker clearing a paper generates
 * one decision per question rather than per script, so a tight limit here would
 * block legitimate work instead of an attack.
 */
class MarkingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AiServiceInterface::class, function (): AiService {
            return new AiService(
                timeout: (int) config('ai.timeout_seconds', 180),
                connectTimeout: 10,
            );
        });

        $this->app->bind(ScriptPipelineServiceInterface::class, ScriptPipelineService::class);
    }

    public function boot(): void
    {
        // Keyed on the authenticated user where there is one, so a school
        // sharing a single network address does not throttle every teacher as
        // though they were one client.
        $key = fn (Request $request): string => (string) ($request->user()?->id ?? $request->ip());

        RateLimiter::for('uploads', fn (Request $request): Limit => Limit::perMinute(30)->by($key($request)));
        RateLimiter::for('marks', fn (Request $request): Limit => Limit::perMinute(240)->by($key($request)));
        RateLimiter::for('approvals', fn (Request $request): Limit => Limit::perMinute(60)->by($key($request)));
        RateLimiter::for('results', fn (Request $request): Limit => Limit::perMinute(20)->by($key($request)));
    }
}