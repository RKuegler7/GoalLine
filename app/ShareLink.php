<?php

namespace App;

/**
 * Links people copy or share (invite links, sign-up links, calendar feeds) are always complete, absolute addresses
 * built from APP_URL, never from whatever host a request happened to arrive on. In production they're always https.
 */
final class ShareLink
{
    /**
     * The absolute public link for a named route.
     *
     * @param  mixed  $parameters
     */
    public static function route(string $name, $parameters = []): string
    {
        return self::to(route($name, $parameters, absolute: false));
    }

    /**
     * The absolute public link for a path like "/invite/abc".
     */
    public static function to(string $path): string
    {
        $root = rtrim((string) config('app.url'), '/');

        if (app()->isProduction()) {
            $root = preg_replace('#^http://#i', 'https://', $root);
        }

        return $root.'/'.ltrim($path, '/');
    }

    /**
     * The message for the phone's share sheet: "Join my GoalLine league, Marathon by March: https://…".
     */
    public static function leagueMessage(string $leagueName, string $link): string
    {
        return "Join my GoalLine league, {$leagueName}: {$link}";
    }
}
