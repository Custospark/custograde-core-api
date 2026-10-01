<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $institution = $user->institution;

        return response()->json([
            'institution' => $institution ? [
                'id' => $institution->id,
                'name' => $institution->name,
                'type' => $institution->type,
            ] : null,
            'stats' => [
                'users' => $institution ? $institution->users()->count() : 0,
                'scripts_marked' => 0,
                'pending_review' => 0,
                'results_released' => 0,
                'appeals_open' => 0,
            ],
        ]);
    }
}
