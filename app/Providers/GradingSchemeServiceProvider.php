<?php

namespace App\Providers;

use App\Repositories\Contracts\GradingSchemeRepositoryInterface;
use App\Repositories\GradingSchemeRepository;
use App\Services\Contracts\GradingSchemeServiceInterface;
use App\Services\GradingSchemeService;
use Illuminate\Support\ServiceProvider;

class GradingSchemeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            GradingSchemeServiceInterface::class,
            GradingSchemeService::class
        );

        $this->app->bind(
            GradingSchemeRepositoryInterface::class,
            GradingSchemeRepository::class
        );
    }

    public function boot(): void
    {
        //
    }
}