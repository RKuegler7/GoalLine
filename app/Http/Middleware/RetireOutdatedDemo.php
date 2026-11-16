<?php

namespace App\Http\Middleware;

use App\Services\DemoWorld;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A demo visitor whose world was built by an older version of the demo (before a deploy changed it) is signed out and
 * sent to "Try the demo", which builds a fresh, current world. Everyone else passes straight through.
 */
class RetireOutdatedDemo
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isDemo() && $user->name === DemoWorld::VISITOR_NAME && ! DemoWorld::isCurrent($user)
            && ! $request->routeIs('demo', 'demo.reset', 'logout')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('demo');
        }

        return $next($request);
    }
}
