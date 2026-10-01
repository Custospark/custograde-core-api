<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\AiServiceInterface;
use Illuminate\Http\JsonResponse;

/**
 * Reports whether the AI service can be used (ADM-03).
 *
 * The dashboard shows this so a teacher is told plainly when the AI is
 * unavailable, rather than discovering it on a paper they have already started
 * marking by hand.
 */
class AiHealthController extends Controller
{
    public function __construct(protected AiServiceInterface $ai) {}

    public function show(): JsonResponse
    {
        return response()->json(['ai' => $this->ai->health()]);
    }
}
