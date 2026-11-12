<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Finds the real visitor behind CloudFront, so every rate limit is per person, not per edge server.
 *
 * TRUSTED_PROXIES (default "REMOTE_ADDR") says which hops to believe. On the single-instance Elastic Beanstalk setup,
 * CloudFront connects straight to the server, so trusting only that caller makes request->ip() the address CloudFront
 * appended to X-Forwarded-For: the visitor's. A visitor who sends their own X-Forwarded-For can't fake it, because
 * their entries sit to the left of the trusted hop. Behind a load balancer as well, set TRUSTED_PROXIES to its
 * private range and turn on CLOUDFRONT_VIEWER_ADDRESS (with the CloudFront-Viewer-Address header added in the
 * CloudFront origin request policy).
 */
class TrustCloudFront extends TrustProxies
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('goalline.proxies.cloudfront_viewer_address') && ($viewer = self::viewerIp($request->headers->get('CloudFront-Viewer-Address')))) {
            // CloudFront's own record of the viewer wins over anything in X-Forwarded-For.
            $request->headers->set('X-Forwarded-For', $viewer);
        }

        return parent::handle($request, $next);
    }

    /**
     * "203.0.113.7:46532" or "2001:db8::1:46532" (the port is always last) to the bare IP, or null if it isn't one.
     */
    public static function viewerIp(?string $header): ?string
    {
        if (! $header || ! str_contains($header, ':')) {
            return null;
        }

        $ip = trim(substr($header, 0, strrpos($header, ':')), '[] ');

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
    }

    protected function proxies()
    {
        return config('goalline.proxies.trusted', 'REMOTE_ADDR');
    }
}
