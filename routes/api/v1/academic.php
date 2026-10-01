<?php

use App\Http\Controllers\Api\AcademicUnitController;
use App\Http\Controllers\Api\AcademicYearController;
use App\Http\Controllers\Api\CourseResourceController;
use App\Http\Controllers\Api\CourseUnitController;
use App\Http\Controllers\Api\GradingSchemeController;
use App\Http\Controllers\Api\TermController;
use Illuminate\Support\Facades\Route;

/*
| Academic setup (ACD-02 to ACD-05): the calendar, the academic hierarchy,
| courses, course documents and grading schemes.
|
| Every route here is authenticated. Nothing in this file is public, because a
| term, a course or a grading scheme only means anything inside an institution.
*/
Route::middleware('auth:sanctum')->group(function () {
    // Reads are deliberately unthrottled: a teacher loading a screen of courses
    // or terms should never be blocked by a rate limit.

    // "current" is declared before the resource show route so it is matched
    // first. Registered after it, it would be swallowed by {id}.
    Route::get('academic-years/current', [AcademicYearController::class, 'current']);
    Route::apiResource('academic-years', AcademicYearController::class)->parameters([
        'academic-years' => 'id',
    ]);

    Route::apiResource('terms', TermController::class)->parameters(['terms' => 'id']);
    Route::post('terms/{id}/activate', [TermController::class, 'activate'])->middleware('throttle:20,1');

    Route::apiResource('org-units', AcademicUnitController::class)->parameters([
        'org-units' => 'id',
    ]);
    Route::post('org-units/{id}/move', [AcademicUnitController::class, 'move'])->middleware('throttle:30,1');

    Route::apiResource('course-units', CourseUnitController::class)->parameters([
        'course-units' => 'id',
    ]);
    Route::post('course-units/{id}/teachers', [CourseUnitController::class, 'attachTeacher'])
        ->middleware('throttle:60,1');
    Route::delete('course-units/{id}/teachers', [CourseUnitController::class, 'detachTeacher'])
        ->middleware('throttle:60,1');

    // Course documents are nested under the course they belong to. A document
    // is always uploaded, never created empty, so the nested POST is multipart.
    Route::get('course-units/{courseUnitId}/resources', [CourseResourceController::class, 'index']);
    Route::post('course-units/{courseUnitId}/resources', [CourseResourceController::class, 'store'])
        ->middleware('throttle:30,1');
    Route::post('course-resources/{id}/current-version', [CourseResourceController::class, 'setCurrentVersion'])
        ->middleware('throttle:30,1');
    Route::apiResource('course-resources', CourseResourceController::class)
        ->parameters(['course-resources' => 'id'])
        ->except(['index', 'store']);

    Route::apiResource('grading-schemes', GradingSchemeController::class)->parameters([
        'grading-schemes' => 'id',
    ]);
    Route::put('grading-schemes/{id}/bands', [GradingSchemeController::class, 'replaceBands'])
        ->middleware('throttle:20,1');
});