<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers on every response:
 * - a Content-Security-Policy: scripts, styles, fonts and connections only from GoalLine itself (images also from
 *   data: and blob:, for photo previews and share cards); no plugins, no framing by other sites, forms only post here.
 *   Inline handlers are still allowed, as a few small ones remain.
 * - HSTS once GoalLine is on https, nosniff, a strict referrer policy, and no camera, microphone or location access.
 * A response that sets its own CSP (share-card images, which are sandboxed) keeps it.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);
        $headers = $response->headers;

        if (! $headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', $this->policy());
        }

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');

        if ($request->isSecure() || str_starts_with((string) config('app.url'), 'https://')) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function policy(): string
    {
        // While `npm run dev` is running, the Vite dev server serves the scripts and styles (and a websocket).
        $dev = '';

        if (Vite::isRunningHot()) {
            $hot = rtrim((string) file_get_contents(Vite::hotFile()));
            $dev = ' '.$hot.' '.preg_replace('#^http#', 'ws', $hot);
        }

        // Assets are linked from APP_URL, which locally can differ from the address in the browser (localhost vs
        // 127.0.0.1), so that origin counts as GoalLine too.
        $app = parse_url((string) config('app.url'));
        $self = "'self'".(isset($app['scheme'], $app['host']) ? " {$app['scheme']}://{$app['host']}".(isset($app['port']) ? ":{$app['port']}" : '') : '');

        return implode('; ', [
            "default-src {$self}",
            "script-src {$self} 'unsafe-inline'{$dev}",
            "style-src {$self} 'unsafe-inline'{$dev}",
            "img-src {$self} data: blob:",
            "font-src {$self} data:{$dev}",
            "connect-src {$self}{$dev}",
            "media-src {$self} blob:",
            "worker-src {$self}",
            "manifest-src {$self}",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action {$self}",
            "frame-ancestors 'self'",
        ]);
    }
}
