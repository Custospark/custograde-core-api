<?php

namespace App\Providers;

use App\Repositories\AcademicYearRepository;
use App\Repositories\Contracts\AcademicYearRepositoryInterface;
use Illuminate\Support\ServiceProvider;

/**
 * Academic years (ACD-03) have no service of their own yet: the year is
 * currently written through TermService, which is where the term and year rules
 * meet. The repository binding is registered here so the year data access has a
 * home alongside the rest of the calendar.
 */
class AcademicYearServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            AcademicYearRepositoryInterface::class,
            AcademicYearRepository::class
        );
    }

    public function boot(): void
    {
        //
    }
}