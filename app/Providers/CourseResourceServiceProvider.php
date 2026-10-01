<?php

namespace App\Providers;

use App\Repositories\Contracts\CourseResourceRepositoryInterface;
use App\Repositories\CourseResourceRepository;
use App\Services\Contracts\CourseResourceServiceInterface;
use App\Services\CourseResourceService;
use Illuminate\Support\ServiceProvider;

class CourseResourceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            CourseResourceServiceInterface::class,
            CourseResourceService::class
        );

        $this->app->bind(
            CourseResourceRepositoryInterface::class,
            CourseResourceRepository::class
        );
    }

    public function boot(): void
    {
        //
    }
}