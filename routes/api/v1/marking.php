<?php

use App\Http\Controllers\Api\AnswerSheetController;
use App\Http\Controllers\Api\AiHealthController;
use App\Http\Controllers\Api\ResultController;
use App\Http\Controllers\Api\ScriptController;
use App\Support\Capability;
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
    Route::get('ai/health', [AiHealthController::class, 'show'])
        ->middleware('capability:'.Capability::VIEW_EXAMS);

    // Scripts for an examination, newest first.
    Route::get('exams/{examId}/scripts', [ScriptController::class, 'index'])
        ->middleware('capability:'.Capability::VIEW_SCRIPTS);
    Route::post('exams/{examId}/scripts', [ScriptController::class, 'store'])
        ->middleware('capability:'.Capability::CAPTURE_SCRIPTS)
        ->middleware('limit:uploads');

    // The review workspace.
    Route::get('scripts/{id}', [ScriptController::class, 'show'])
        ->middleware('capability:'.Capability::VIEW_SCRIPTS);
    // The scan itself, as a short-lived signed URL rather than a stored path.
    Route::get('scripts/{id}/image', [ScriptController::class, 'image'])
        ->middleware('capability:'.Capability::VIEW_SCRIPTS);
    Route::post('scripts/{id}/reprocess', [ScriptController::class, 'reprocess'])
        ->middleware('capability:'.Capability::CAPTURE_SCRIPTS)
        ->middleware('limit:results');

    // The only route that writes a mark. Every other mark in the system is a
    // proposal until it passes through here with an authenticated person.
    Route::post('scripts/{id}/answers/{answer}/mark', [ScriptController::class, 'decideMark'])
        ->middleware(['capability:'.Capability::DECIDE_MARKS, 'limit:marks']);
    Route::put('scripts/{id}/answers/{answer}/transcription', [ScriptController::class, 'correctTranscription'])
        ->middleware(['capability:'.Capability::DECIDE_MARKS, 'limit:marks']);

    Route::post('scripts/{id}/lock', [ScriptController::class, 'lock'])
        ->middleware(['capability:'.Capability::LOCK_SCRIPTS, 'limit:approvals']);
    Route::post('scripts/{id}/unlock', [ScriptController::class, 'unlock'])
        // Reopening an approved mark is not the same power as making one, so it
        // is gated separately (SEC-07).
        ->middleware(['capability:'.Capability::AMEND_APPROVED_MARKS, 'limit:approvals']);
    Route::post('scripts/{id}/flag', [ScriptController::class, 'flag'])
        ->middleware(['capability:'.Capability::FLAG_SCRIPTS, 'limit:approvals']);

    // Results.
    // Answer sheets (SHT). Issuing a sheet creates the script row up front,
    // which is what makes a scan resolvable to a candidate later on (IDN-02).
    Route::get('exams/{examId}/sheets', [AnswerSheetController::class, 'index'])
        ->middleware('capability:'.Capability::VIEW_EXAMS);
    Route::post('exams/{examId}/sheets', [AnswerSheetController::class, 'store'])
        ->middleware('capability:'.Capability::BUILD_ANSWER_SHEETS);
    Route::post('exams/{examId}/sheets/{scriptId}/reissue', [AnswerSheetController::class, 'reissue'])
        ->middleware('capability:'.Capability::BUILD_ANSWER_SHEETS);
    Route::get('exams/{examId}/sheets/{scriptId}', [AnswerSheetController::class, 'download'])
        ->middleware('capability:'.Capability::BUILD_ANSWER_SHEETS);

    Route::get('exams/{examId}/results', [ResultController::class, 'index'])
        ->middleware('capability:'.Capability::VIEW_RESULTS);
    Route::get('exams/{examId}/results/statistics', [ResultController::class, 'statistics'])
        ->middleware('capability:'.Capability::VIEW_RESULTS);
    Route::post('exams/{examId}/results/compile', [ResultController::class, 'compile'])
        ->middleware('capability:'.Capability::COMPILE_RESULTS)
        ->middleware('limit:results');
    Route::post('exams/{examId}/results/release', [ResultController::class, 'release'])
        ->middleware('capability:'.Capability::RELEASE_RESULTS)
        ->middleware('limit:results');
    Route::post('exams/{examId}/results/withhold', [ResultController::class, 'withhold'])
        ->middleware('capability:'.Capability::RELEASE_RESULTS)
        ->middleware('limit:results');
});
