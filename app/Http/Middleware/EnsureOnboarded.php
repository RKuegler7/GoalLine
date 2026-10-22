<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboarded
{
    /**
     * Pages a newcomer can use before finishing the welcome walkthrough.
     *
     * @var list<string>
     */
    private const ALLOWED_DURING_WELCOME = [
        'welcome.*', 'logout', 'verification.*', 'avatars.show', 'covers.show',
        'goals.store', 'leagues.find', 'leagues.join', 'leagues.join.store', 'people.index', 'follows.*', 'profiles.show',
    ];

    /**
     * Send people who haven't been through the welcome walkthrough to it first.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->onboarded_at === null && ! $request->routeIs(...self::ALLOWED_DURING_WELCOME)) {
            return redirect()->route('welcome.show');
        }

        return $next($request);
    }
}
