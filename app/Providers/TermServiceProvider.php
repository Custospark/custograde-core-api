<?php

namespace App\Providers;

use App\Repositories\Contracts\TermRepositoryInterface;
use App\Repositories\TermRepository;
use App\Services\Contracts\TermServiceInterface;
use App\Services\TermService;
use Illuminate\Support\ServiceProvider;

class TermServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            TermServiceInterface::class,
            TermService::class
        );

        $this->app->bind(
            TermRepositoryInterface::class,
            TermRepository::class
        );
    }

    public function boot(): void
    {
        //
    }
}