<?php

namespace App\Providers;

use App\Services\AiService;
use App\Services\Contracts\AiServiceInterface;
use App\Services\Contracts\ScriptPipelineServiceInterface;
use App\Services\ScriptPipelineService;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the marking chain by interface.
 *
 * The AI service client takes its timeouts from config, so they are bound
 * through a closure rather than the container resolving constructor defaults.
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
        //
    }
}
