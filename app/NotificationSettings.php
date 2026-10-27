<?php

namespace App;

/**
 * What each person wants to hear about, and when their daily reminders arrive on their own clock.
 * Everything social lands in the Inbox regardless; these settings choose what also goes to push and email.
 */
final class NotificationSettings
{
    /**
     * Each kind of notification, with how it's described in settings.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const CATEGORIES = [
        'reminders' => ['Daily reminders', 'Your morning gameplan, and an evening nudge when there is still work left.'],
        'recap' => ['Weekly recap', 'Sunday evening: your week in check-ins, points, streak, and wins.'],
        'results' => ['GoalWeek results', 'When a GoalWeek is final, with your result and points.'],
        'badges' => ['Badges and awards', 'When you earn a badge or win a GoalLeague award.'],
        'leagues' => ['GoalLeagues and challenges', 'Weekly and season recaps, challenge results, and people joining.'],
        'mentions' => ['Comments and mentions', 'When someone comments on your highlight or @mentions you.'],
        'nudges' => ['Nudges', 'When a friend or league mate nudges you to check in.'],
        'social' => ['Kudos and friends', 'When someone gives you kudos, sends a friend request, or accepts yours.'],
        'news' => ['GoalLine news', 'New features and the occasional note from the team.'],
    ];

    /**
     * Email is on for the things that keep you on track, off for the chattier ones.
     *
     * @var array<string, bool>
     */
    public const EMAIL_DEFAULTS = [
        'reminders' => true,
        'recap' => true,
        'results' => true,
        'badges' => true,
        'leagues' => true,
        'mentions' => true,
        'nudges' => false,
        'social' => false,
        'news' => true,
    ];

    /**
     * Push is on for everything except news, once someone turns it on for a device.
     *
     * @var array<string, bool>
     */
    public const PUSH_DEFAULTS = [
        'reminders' => true,
        'recap' => true,
        'results' => true,
        'badges' => true,
        'leagues' => true,
        'mentions' => true,
        'nudges' => true,
        'social' => true,
        'news' => false,
    ];

    public const MORNING_DEFAULT = '07:30';

    public const EVENING_DEFAULT = '20:00';

    /**
     * Times people can choose from, on their own clock.
     *
     * @var list<string>
     */
    public const MORNING_TIMES = ['05:00', '05:30', '06:00', '06:30', '07:00', '07:30', '08:00', '08:30', '09:00', '09:30', '10:00', '11:00', '12:00'];

    /** @var list<string> */
    public const EVENING_TIMES = ['16:00', '17:00', '18:00', '19:00', '19:30', '20:00', '20:30', '21:00', '21:30', '22:00', '23:00'];

    /**
     * @param  array<string, bool>  $email
     * @param  array<string, bool>  $push
     */
    public function __construct(
        public readonly array $email,
        public readonly array $push,
        public readonly ?string $morningAt,
        public readonly ?string $eveningAt,
    ) {}

    /**
     * Build from what's stored on a person, filling in defaults for anything never set.
     *
     * @param  array<string, mixed>|null  $stored
     */
    public static function from(?array $stored): self
    {
        $stored ??= [];

        return new self(
            email: collect(self::EMAIL_DEFAULTS)->map(fn (bool $default, string $category) => (bool) ($stored['email'][$category] ?? $default))->all(),
            push: collect(self::PUSH_DEFAULTS)->map(fn (bool $default, string $category) => (bool) ($stored['push'][$category] ?? $default))->all(),
            morningAt: array_key_exists('morning_at', $stored) ? $stored['morning_at'] : self::MORNING_DEFAULT,
            eveningAt: array_key_exists('evening_at', $stored) ? $stored['evening_at'] : self::EVENING_DEFAULT,
        );
    }

    /**
     * Whether a kind of notification should also go to email.
     */
    public function wantsEmail(string $category): bool
    {
        return $this->email[$category] ?? false;
    }

    /**
     * Whether a kind of notification should also go to the member's phones and browsers with push turned on.
     */
    public function wantsPush(string $category): bool
    {
        return $this->push[$category] ?? false;
    }

    /**
     * @return array{email: array<string, bool>, push: array<string, bool>, morning_at: string|null, evening_at: string|null}
     */
    public function toArray(): array
    {
        return ['email' => $this->email, 'push' => $this->push, 'morning_at' => $this->morningAt, 'evening_at' => $this->eveningAt];
    }

    /**
     * "7:30 AM" from "07:30".
     */
    public static function label(string $time): string
    {
        return date('g:i A', strtotime("2000-01-01 {$time}"));
    }
}
