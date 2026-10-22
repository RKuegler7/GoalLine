<?php

namespace App\Http\Middleware;

use App\Usage;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackActivity
{
    /**
     * Count each signed-in member once a day as active.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->isMethod('GET')) {
            Usage::trackActive($request->user());
        }

        return $next($request);
    }
}
