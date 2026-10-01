<?php

use App\Providers\AcademicUnitServiceProvider;
use App\Providers\AcademicYearServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\CourseResourceServiceProvider;
use App\Providers\CourseUnitServiceProvider;
use App\Providers\ExaminationServiceProvider;
use App\Providers\GradingSchemeServiceProvider;
use App\Providers\MarkingServiceProvider;
use App\Providers\TermServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,

    // Academic structure (ACD-02 to ACD-05).
    AcademicYearServiceProvider::class,
    AcademicUnitServiceProvider::class,
    TermServiceProvider::class,
    CourseUnitServiceProvider::class,
    CourseResourceServiceProvider::class,
    GradingSchemeServiceProvider::class,
    MarkingServiceProvider::class,

    // Papers, questions and the roster (EXM-01 to EXM-03, EXM-06, STU-01, STU-03).
    ExaminationServiceProvider::class,
];
