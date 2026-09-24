<?php // bootstrap/app.php

use Illuminate\Foundation\Application;
use App\Http\Middleware\DeviceApiKeyMiddleware;
use App\Http\Middleware\TrackPageVisit;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        App\Providers\AppServiceProvider::class,
        App\Providers\Filament\AdminPanelProvider::class,
        App\Providers\Filament\UserPanelProvider::class,
        App\Providers\AuthServiceProvider::class,
        App\Providers\BladeFilamentBridgeProvider::class,
    ])

    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // alias felvétel (Laravel 11 way)
        $middleware->alias([
            'device.api.key' => DeviceApiKeyMiddleware::class,
            'track.visit' => TrackPageVisit::class,

        ]);
        $middleware->append(\App\Http\Middleware\TrustHosts::class);
    })
    ->withExceptions(function ($exceptions) {
        //
    })->create();
