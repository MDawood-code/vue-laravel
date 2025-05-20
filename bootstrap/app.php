<?php

use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders()
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectUsersTo(AppServiceProvider::HOME);

        $middleware->throttleApi();

        $middleware->replace(\Illuminate\Http\Middleware\TrustHosts::class, \App\Http\Middleware\TrustHosts::class);

        $middleware->alias([
            'admin-or-agent' => \App\Http\Middleware\CheckAdminOrAgent::class,
            'auth' => \App\Http\Middleware\Authenticate::class,
            'only-admin' => \App\Http\Middleware\CheckAdmin::class,
            'only-referral' => \App\Http\Middleware\CheckReferral::class,
            'only-reseller' => \App\Http\Middleware\CheckReseller::class,
            'only-super-admin' => \App\Http\Middleware\CheckSuperAdmin::class,
            'track-company-active' => \App\Http\Middleware\TrackCompanyLastActiveAt::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->reportable(function (Throwable $e): void {
            //
        });
    })->create();
