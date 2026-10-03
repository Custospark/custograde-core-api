<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
->withMiddleware(function (Middleware $middleware): void {
        // `limit:` resolves a named rate limiter, so each marking action has its
        // own allowance. The inline `throttle:10,1` form shares one counter per
        // user across every route, which would make a teacher throttling
        // themselves out of approving the scripts they just uploaded.
        $middleware->alias([
            'limit' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
            // Answers "are you allowed to do this", which is a different
            // question from "is this your row". Tenants are already scoped in
            // every repository; without this a teacher could release results.
            'capability' => \App\Http\Middleware\RequireCapability::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
