<?php

namespace App\Http\Middleware;

use App\Clock;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UseViewerTimezone
{
    /**
     * Show dates and run GoalWeek logic on the signed-in person's clock.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Clock::useTimezoneOf($request->user());

        return $next($request);
    }
}
