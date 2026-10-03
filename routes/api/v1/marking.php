<?php

use App\Http\Controllers\Api\AiHealthController;
use App\Http\Controllers\Api\ResultController;
use App\Http\Controllers\Api\ScriptController;
use Illuminate\Support\Facades\Route;

/*
| Marking and results.
|
| Script capture and reading (CAP, OCR), the review workspace where a human
| approves every mark (REV), and result compilation and release (MRK).
|
| Everything here is authenticated. A script is a candidate's work, and a mark
| is a record about a person, so none of it is ever public.
|
| One route in this file writes a mark: the decide route. The pipeline proposes
| and this is where a teacher accepts, adjusts or rejects (BR-02).
*/
Route::middleware('auth:sanctum')->group(function () {
    // Whether the AI service is reachable and configured (ADM-03). Read often
    // by the dashboard, so it is deliberately unthrottled.
    Route::get('ai/health', [AiHealthController::class, 'show']);

    // Scripts for an examination, newest first.
    Route::get('exams/{examId}/scripts', [ScriptController::class, 'index']);
    Route::post('exams/{examId}/scripts', [ScriptController::class, 'store'])
        ->middleware('limit:uploads');

    // The review workspace.
    Route::get('scripts/{id}', [ScriptController::class, 'show']);
    // The scan itself, as a short-lived signed URL rather than a stored path.
    Route::get('scripts/{id}/image', [ScriptController::class, 'image']);
    Route::post('scripts/{id}/reprocess', [ScriptController::class, 'reprocess'])
        ->middleware('limit:results');

    // The only route that writes a mark. Every other mark in the system is a
    // proposal until it passes through here with an authenticated person.
    Route::post('scripts/{id}/answers/{answer}/mark', [ScriptController::class, 'decideMark'])
        ->middleware('limit:marks');
    Route::put('scripts/{id}/answers/{answer}/transcription', [ScriptController::class, 'correctTranscription'])
        ->middleware('limit:marks');

    Route::post('scripts/{id}/lock', [ScriptController::class, 'lock'])->middleware('limit:approvals');
    Route::post('scripts/{id}/unlock', [ScriptController::class, 'unlock'])->middleware('limit:approvals');
    Route::post('scripts/{id}/flag', [ScriptController::class, 'flag'])->middleware('limit:approvals');

    // Results.
    Route::get('exams/{examId}/results', [ResultController::class, 'index']);
    Route::get('exams/{examId}/results/statistics', [ResultController::class, 'statistics']);
    Route::post('exams/{examId}/results/compile', [ResultController::class, 'compile'])
        ->middleware('limit:results');
    Route::post('exams/{examId}/results/release', [ResultController::class, 'release'])
        ->middleware('limit:results');
    Route::post('exams/{examId}/results/withhold', [ResultController::class, 'withhold'])
        ->middleware('limit:results');
});
