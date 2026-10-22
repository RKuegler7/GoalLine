<?php

use App\Http\Middleware\EnsureOnboarded;
use App\Http\Middleware\TrackActivity;
use App\Http\Middleware\UseViewerTimezone;
use App\Notifications\AdminAlert;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [UseViewerTimezone::class, EnsureOnboarded::class, TrackActivity::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Email the admins when something breaks: once an hour per distinct error, so a bad day isn't a flood.
        $exceptions->report(function (Throwable $exception) {
            $admins = config('goalline.admin_emails');

            if ($admins === [] || app()->environment('testing')) {
                return;
            }

            $fingerprint = sha1($exception::class.$exception->getFile().$exception->getLine());

            if (! Cache::add("error-alert:{$fingerprint}", true, now()->addHour())) {
                return;
            }

            try {
                Notification::route('mail', $admins)->notifyNow(new AdminAlert(
                    'Error: '.class_basename($exception).' in '.basename($exception->getFile()).':'.$exception->getLine(),
                    str($exception->getMessage())->limit(500).' (Full details are in the log.)',
                ));
            } catch (Throwable) {
                // Never let an alert failure hide the original error.
            }
        });
    })->create();
