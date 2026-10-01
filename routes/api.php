<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('health', function () {
        return response()->json([
            'status' => 'ok',
            'app' => 'CustoGrade',
            'version' => config('app.version', '0.1.0'),
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    // Domain route files (same pattern as Custosell):
    // require __DIR__ . '/api/v1/<entity>.php';
});
