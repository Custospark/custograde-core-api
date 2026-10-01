<?php

namespace App\Providers;

use App\Repositories\Contracts\ExamRepositoryInterface;
use App\Repositories\Contracts\StudentRepositoryInterface;
use App\Repositories\ExamRepository;
use App\Repositories\StudentRepository;
use App\Services\Contracts\ExamServiceInterface;
use App\Services\Contracts\StudentServiceInterface;
use App\Services\ExamService;
use App\Services\StudentService;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the examinations and roster by interface.
 *
 * Both repositories are bound here as well as both services. A repository left
 * unbound would surface as an unbound interface exception at the first request
 * that needed it, not at boot, so both are declared in the one place that owns
 * the wiring.
 */
class ExaminationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ExamServiceInterface::class, ExamService::class);
        $this->app->bind(StudentServiceInterface::class, StudentService::class);
        $this->app->bind(ExamRepositoryInterface::class, ExamRepository::class);
        $this->app->bind(StudentRepositoryInterface::class, StudentRepository::class);
    }

    public function boot(): void
    {
        //
    }
}