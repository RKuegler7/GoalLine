<?php

use App\Http\Controllers\HealthController;
use App\Http\Middleware\EnsureOnboarded;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrackActivity;
use App\Http\Middleware\TrustCloudFront;
use App\Http\Middleware\UsePublicAddress;
use App\Http\Middleware\UseViewerTimezone;
use App\Services\ErrorAlerts;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // /health sits outside the web middleware (no session, cookies or CSRF), so it still answers, with a 503, when
        // the database that holds sessions is down.
        then: function (): void {
            Route::get('/health', HealthController::class)->name('health');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [UseViewerTimezone::class, EnsureOnboarded::class, TrackActivity::class]);

        // CloudFront sits in front in production: find the real visitor behind it (TRUSTED_PROXIES, see
        // TrustCloudFront), and treat every request as arriving at the public https address so signed links validate.
        $middleware->replace(TrustProxies::class, TrustCloudFront::class);
        $middleware->prepend(UsePublicAddress::class);

        // Security headers (CSP, HSTS, nosniff…) on every response, error pages included.
        $middleware->append(SecurityHeaders::class);
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
