<?php

namespace App;

use App\Models\User;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Str;

/**
 * The one place that decides whether GoalLine may email (or push to) someone. Every email path asks here:
 *
 * - Member notifications (App\Notifications\GoalLineNotification::via), by stream:
 *   reminders: DailyReminder.
 *   nudges: Nudged, ProofRequested ("Show your work").
 *   recaps: WeeklyRecapReady (the Monday recap), GoalWeeksFinal, LeagueRecap, ChallengeFinished, CoachTipReady.
 *   notifications: everything else: DisputeOpened, DisputeResolved, GroupTaskProposed, GroupTaskDecided,
 *   FriendRequested, FriendAccepted, FriendHighlight, LeagueInvited, LeagueMemberJoined, ChallengeJoined,
 *   HighlightCheered, HighlightCommented, Mentioned, BadgeEarned, Announcement.
 * - Transactional, never switched off: email verification and password reset (User), and LeagueInviteEmail (an
 *   invite a member chose to send by email).
 * - Not member messaging: AdminAlert (errors, AI spend and feedback, to ADMIN_EMAILS only).
 *
 * Demo accounts never get email, whatever the switches say.
 */
final class Outbound
{
    public const REMINDERS = 'email_reminders';

    public const NUDGES = 'email_nudges';

    public const RECAPS = 'email_recaps';

    public const NOTIFICATIONS = 'email_notifications';

    /**
     * Whether a stream of member email is switched on (config/goalline.php, GOALLINE_EMAIL_*).
     */
    public static function emailEnabled(string $stream): bool
    {
        return (bool) config("goalline.messaging.{$stream}", false);
    }

    /**
     * Whether push notifications are switched on (GOALLINE_PUSH).
     */
    public static function pushEnabled(): bool
    {
        return (bool) config('goalline.messaging.push', false);
    }

    /**
     * Whether an email may go to this recipient. A null stream means transactional: always on, except for demo
     * accounts (and the made-up demo addresses), which never get email.
     */
    public static function mayEmail(object $notifiable, ?string $stream = null): bool
    {
        if (self::isDemoRecipient($notifiable)) {
            return false;
        }

        return $stream === null || self::emailEnabled($stream);
    }

    private static function isDemoRecipient(object $notifiable): bool
    {
        if ($notifiable instanceof User) {
            return $notifiable->isDemo() || Str::endsWith(strtolower((string) $notifiable->email), '.invalid');
        }

        if ($notifiable instanceof AnonymousNotifiable) {
            $address = $notifiable->routeNotificationFor('mail');

            return collect((array) $address)->keys()->merge((array) $address)
                ->contains(fn ($value) => is_string($value) && Str::endsWith(strtolower($value), '.invalid'));
        }

        return false;
    }
}
