<?php

namespace App;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Whose day it is. GoalWeeks, check-in deadlines, and trials follow each person's own time zone,
 * while the database keeps one canonical clock (the app time zone) so stored times never get mixed up.
 */
final class Clock
{
    private static ?string $timezone = null;

    /**
     * Use this person's time zone for the rest of the request (or job).
     */
    public static function useTimezoneOf(?User $user): void
    {
        self::$timezone = $user?->timezoneName();
    }

    /**
     * Run code on someone else's clock, such as a queued job working on their goal.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function for(?User $user, callable $callback): mixed
    {
        $previous = self::$timezone;
        self::useTimezoneOf($user);

        try {
            return $callback();
        } finally {
            self::$timezone = $previous;
        }
    }

    /**
     * The time zone in use.
     */
    public static function timezone(): string
    {
        return self::$timezone ?? config('app.timezone');
    }

    /**
     * Today's date in the person's time zone, as midnight on the app clock so it compares cleanly with date columns.
     */
    public static function today(): Carbon
    {
        return Carbon::parse(now(self::timezone())->toDateString(), config('app.timezone'));
    }

    /**
     * A stored moment shown in the person's time zone.
     */
    public static function local(?CarbonInterface $moment): ?Carbon
    {
        return $moment ? Carbon::instance($moment)->setTimezone(self::timezone()) : null;
    }

    /**
     * A short time for chat and activity: "4:55 pm" today, otherwise "Oct 21, 4:55 pm".
     */
    public static function shortTime(CarbonInterface $moment): string
    {
        $local = self::local($moment);

        return $local->isSameDay(now(self::timezone())) ? $local->format('g:i a') : $local->format('M j, g:i a');
    }
}
