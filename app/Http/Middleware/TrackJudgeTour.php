<?php

namespace App\Http\Middleware;

use App\Services\JudgeTour;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ticks off a Judge's tour item when a demo visitor does it: a successful request to one of JudgeTour::ROUTES.
 * Everyone else passes straight through.
 */
class TrackJudgeTour
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $item = JudgeTour::ROUTES[$request->route()?->getName() ?? ''] ?? null;

        if ($item !== null && $response->getStatusCode() < 400 && JudgeTour::applies($request->user())) {
            JudgeTour::mark($request->user(), $item);
        }

        return $response;
    }
}
