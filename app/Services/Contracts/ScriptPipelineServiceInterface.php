<?php

namespace App\Services\Contracts;

use App\Models\Script;

/**
 * Reads a captured script and proposes marks for it.
 *
 * Behind an interface so a controller depends on the contract rather than the
 * pipeline, and so a test can substitute a fake that never calls a model.
 */
interface ScriptPipelineServiceInterface
{
    /**
     * @return array<string, mixed> A report of what was read and proposed.
     *
     * @throws \App\Services\AiServiceException
     */
    public function process(Script $script): array;
}
