<?php

use App\Http\Middleware\EnsureOnboarded;
use App\Http\Middleware\TrackActivity;
use App\Http\Middleware\UsePublicAddress;
use App\Http\Middleware\UseViewerTimezone;
use App\Services\ErrorAlerts;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [UseViewerTimezone::class, EnsureOnboarded::class, TrackActivity::class]);

        // CloudFront sits in front in production: trust its forwarded client address, and treat every
        // request as arriving at the public https address so signed links validate.
        $middleware->trustProxies(at: '*');
        $middleware->prepend(UsePublicAddress::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Email the admins when something breaks: once an hour per distinct error, so a bad day isn't a flood.
        $exceptions->report(function (Throwable $exception) {
            if (! app()->environment('testing')) {
                app(ErrorAlerts::class)->report($exception);
            }
        });
    })->create();
