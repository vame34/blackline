<?php

use App\Http\Middleware\ApiSecurityTelemetry;
use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\EnsureUserNotBanned;
use App\Http\Middleware\NoStorePageCache;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TelemetryContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            TelemetryContext::class,
            SetLocale::class,
        ]);

        $middleware->alias([
            'auth.api_token' => AuthenticateApiToken::class,
            'api.security_telemetry' => ApiSecurityTelemetry::class,
            'no.store' => NoStorePageCache::class,
            'not.banned' => EnsureUserNotBanned::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
