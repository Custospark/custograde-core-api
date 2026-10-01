<?php

use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\StudentController;
use Illuminate\Support\Facades\Route;

/*
| Examinations and the roster (EXM-01 to EXM-03, EXM-06, STU-01, STU-03).
|
| Every route here is authenticated. Nothing in this file is public, because a
| paper, a question or a candidate only means anything inside an institution.
| Cross-tenant access is refused in the services, so a valid id belonging to
| another school is reported as not found rather than as forbidden.
|
| Reads are deliberately unthrottled: a teacher loading the paper builder should
| never be blocked by a rate limit. Writes are throttled, because a paper builder
| retries saves and a bulk roster entry can produce a burst of requests that is
| far more likely to be a stuck client than a teacher in a hurry.
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('exams', ExamController::class)->parameters(['exams' => 'id']);

    Route::post('exams/{id}/questions', [ExamController::class, 'addQuestion'])
        ->middleware('throttle:120,1');
    Route::put('exams/{id}/questions/{question}', [ExamController::class, 'updateQuestion'])
        ->middleware('throttle:120,1');
    Route::delete('exams/{id}/questions/{question}', [ExamController::class, 'deleteQuestion'])
        ->middleware('throttle:120,1');
    Route::put('exams/{id}/status', [ExamController::class, 'setStatus'])
        ->middleware('throttle:30,1');
    Route::post('exams/{id}/recalculate-totals', [ExamController::class, 'recalculateTotals'])
        ->middleware('throttle:20,1');

    Route::post('exams/{id}/students', [StudentController::class, 'enrol'])
        ->middleware('throttle:60,1');
    Route::delete('exams/{id}/students/{student}', [StudentController::class, 'unenrol'])
        ->middleware('throttle:60,1');

    Route::apiResource('students', StudentController::class)->parameters(['students' => 'id']);
});