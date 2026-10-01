<?php

namespace App\Providers;

use App\Repositories\Contracts\OrgUnitRepositoryInterface;
use App\Repositories\OrgUnitRepository;
use App\Services\AcademicUnitService;
use App\Services\Contracts\AcademicUnitServiceInterface;
use Illuminate\Support\ServiceProvider;

class AcademicUnitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            AcademicUnitServiceInterface::class,
            AcademicUnitService::class
        );

        $this->app->bind(
            OrgUnitRepositoryInterface::class,
            OrgUnitRepository::class
        );
    }

    public function boot(): void
    {
        //
    }
}