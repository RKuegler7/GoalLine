<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * In production GoalLine sits behind CloudFront: visitors use https://…cloudfront.net, but the server receives
 * plain HTTP under its own Elastic Beanstalk name. Treat every request as arriving at APP_URL, so signed links
 * (email verification, password resets) validate and nobody can spoof the host name.
 */
class UsePublicAddress
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('goalline.behind_cloudfront')) {
            $address = parse_url((string) config('app.url'));
            $secure = ($address['scheme'] ?? 'https') === 'https';

            $request->headers->set('HOST', $address['host'] ?? $request->getHost());
            $request->server->set('HTTP_HOST', $address['host'] ?? $request->getHost());
            $request->server->set('HTTPS', $secure ? 'on' : 'off');
            $request->server->set('SERVER_PORT', $address['port'] ?? ($secure ? 443 : 80));
            $request->headers->remove('X-Forwarded-Host');
            $request->headers->remove('X-Forwarded-Proto');
            $request->headers->remove('X-Forwarded-Port');
        }

        return $next($request);
    }
}
