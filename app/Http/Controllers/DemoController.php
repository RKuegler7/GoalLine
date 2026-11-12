<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DemoWorld;
use App\Usage;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

class DemoController extends Controller
{
    /**
     * The cookie that remembers this browser's demo, so "Try the demo" comes back to it instead of building a new one.
     */
    public const COOKIE = 'goalline_demo';

    /**
     * A random id for this browser, so demo limits can tell apart people who share one network.
     */
    public const BROWSER_COOKIE = 'goalline_browser';

    /**
     * One tap into GoalLine. If this browser already has a working demo, it opens again (never a new one, never
     * blocked). Otherwise a fresh demo world is built, within generous limits per network, per browser and in total.
     * Expired demo worlds are cleaned up right here, so cleanup never depends on the scheduler.
     */
    public function start(Request $request, DemoWorld $world): RedirectResponse
    {
        abort_unless(config('goalline.demo.enabled'), 404);
        $current = $request->user();

        if ($current && ! $current->isDemo()) {
            return redirect()->route('dashboard')->with('status', "You're signed in to your own account. Log out first to try the demo.");
        }

        // Already in a demo: keep going. "Reset demo" is the way to start over.
        if ($current) {
            return redirect()->route('dashboard')->with('status', 'Welcome back to your demo. Use Reset demo to start fresh.');
        }

        if ($returning = $this->rememberedDemo($request)) {
            Auth::login($returning);
            $request->session()->regenerate();

            return redirect()->route('dashboard')->with('status', 'Welcome back to your demo. Use Reset demo to start fresh.');
        }

        $browser = $request->cookie(self::BROWSER_COOKIE) ?: Str::random(32);
        $this->limit($request, $browser);

        DemoWorld::pruneExpired();
        Cookie::queue(self::BROWSER_COOKIE, $browser, 60 * 24 * 30);

        return $this->build($request, $world, 'Welcome to the GoalLine demo. Look around: check off a task, cheer a post, or vote on the group task.');
    }

    /**
     * "Reset demo": throw away this visitor's world and build them a fresh copy.
     */
    public function reset(Request $request, DemoWorld $world): RedirectResponse
    {
        abort_unless(config('goalline.demo.enabled') && $request->user()?->isDemo(), 404);
        $sandbox = $request->user()->demo_sandbox;

        Auth::logout();
        User::where('demo_sandbox', $sandbox)->get()->each(fn (User $user) => DemoWorld::remove($user));
        DemoWorld::pruneExpired();

        return $this->build($request, $world, 'Your demo is reset: a fresh copy of the same world.');
    }

    /**
     * The demo visitor this browser already has, if it still exists.
     */
    private function rememberedDemo(Request $request): ?User
    {
        $id = (int) $request->cookie(self::COOKIE);

        return $id > 0 ? User::whereKey($id)->whereNotNull('demo_sandbox')->where('name', DemoWorld::VISITOR_NAME)->first() : null;
    }

    /**
     * New demos per network address, per browser, and in total, all per hour. Over a limit: the friendly 429 page with
     * a countdown. A visitor with a demo never gets here.
     */
    private function limit(Request $request, string $browser): void
    {
        $limits = [
            'demo-ip:'.$request->ip() => config('goalline.demo.starts_per_hour'),
            'demo-browser:'.$browser => config('goalline.demo.starts_per_hour_per_browser'),
            'demo-total' => config('goalline.demo.starts_per_hour_total'),
        ];

        foreach ($limits as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $wait = RateLimiter::availableIn($key);

                throw new ThrottleRequestsException('Too Many Attempts.', null, ['Retry-After' => $wait]);
            }
        }

        foreach (array_keys($limits) as $key) {
            RateLimiter::hit($key, 3600);
        }
    }

    /**
     * Build a demo world and sign the visitor in. If building fails, the error is logged for the admins and the
     * visitor gets a friendly note instead of an error page.
     */
    private function build(Request $request, DemoWorld $world, string $status): RedirectResponse
    {
        try {
            $visitor = $world->create();
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('login')->with('status', 'The demo couldn\'t start just now. Please try again in a minute.');
        }

        return $this->signIn($request, $visitor, $status);
    }

    private function signIn(Request $request, User $visitor, string $status): RedirectResponse
    {
        Usage::track($visitor, 'demo-started');

        Auth::login($visitor);
        $request->session()->regenerate();
        // Remember this demo for as long as it lives, so the next tap opens it again.
        Cookie::queue(self::COOKIE, (string) $visitor->id, 60 * (int) config('goalline.demo.ttl_hours'));

        return redirect()->route('dashboard')->with('status', $status);
    }
}
