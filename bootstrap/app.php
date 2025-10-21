<?php // bootstrap/app.php

use Illuminate\Foundation\Application;
use App\Http\Middleware\DeviceTokenAuth;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
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
            'auth.device' => DeviceTokenAuth::class,

        ]);
        $middleware->append(\App\Http\Middleware\TrustHosts::class);
        // ha globálisan akarnád minden kérésre:
        // $middleware->append(\App\Http\Middleware\DeviceTokenAuth::class);

        // ha egy meglévő csoporthoz (pl. 'api') akarod hozzáadni:
        // $middleware->appendToGroup('api', \App\Http\Middleware\DeviceTokenAuth::class);
    })
    ->withExceptions(function ($exceptions) {
        //
    })->create();
