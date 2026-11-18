<?php

use App\Atomic;
use App\Http\Controllers\HealthController;
use App\Http\Middleware\EnsureOnboarded;
use App\Http\Middleware\RetireOutdatedDemo;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrackActivity;
use App\Http\Middleware\TrackJudgeTour;
use App\Http\Middleware\TrustCloudFront;
use App\Http\Middleware\UsePublicAddress;
use App\Http\Middleware\UseViewerTimezone;
use App\Services\ErrorAlerts;
use Illuminate\Database\QueryException;
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
        $middleware->web(append: [RetireOutdatedDemo::class, UseViewerTimezone::class, EnsureOnboarded::class, TrackActivity::class, TrackJudgeTour::class]);

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

        // A save that still lost a database deadlock after its retries (many people at once): back to the same page
        // with a calm note to try again, never an error page. It's still reported below.
        $exceptions->render(function (QueryException $exception, Request $request) {
            if (! $request->isMethod('GET') && ! $request->expectsJson() && Atomic::isConcurrencyError($exception)) {
                return back()->with('status', 'Busy moment, so that didn\'t save. Please try again.');
            }

            return null;
        });

        // Email the admins when something breaks: once every 15 minutes per distinct error, so a bad day isn't a flood.
        $exceptions->report(function (Throwable $exception) {
            if (! app()->environment('testing')) {
                app(ErrorAlerts::class)->report($exception);
            }
        });
    })->create();
