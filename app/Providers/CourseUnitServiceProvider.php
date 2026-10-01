<?php

namespace App\Providers;

use App\Repositories\Contracts\CourseUnitRepositoryInterface;
use App\Repositories\CourseUnitRepository;
use App\Services\Contracts\CourseUnitServiceInterface;
use App\Services\CourseUnitService;
use Illuminate\Support\ServiceProvider;

class CourseUnitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            CourseUnitServiceInterface::class,
            CourseUnitService::class
        );

        $this->app->bind(
            CourseUnitRepositoryInterface::class,
            CourseUnitRepository::class
        );
    }

    public function boot(): void
    {
        //
    }
}