<?php

namespace App\Models;

use App\NotificationSettings;
use App\ProfileVisibility;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'username', 'bio', 'location', 'timezone', 'profile_visibility', 'discoverable'])]
#[Hidden(['password', 'remember_token', 'calendar_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Profile photos are kept on the private disk and served through a route.
     */
    public const AVATAR_DISK = 'uploads';

    /**
     * How long before the same person can be nudged again.
     */
    public const NUDGE_COOLDOWN_HOURS = 20;

    /**
     * Default values for new members.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'profile_visibility' => 'leagues',
    ];

    /**
     * Give every new member a handle they can change later.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->username ??= self::availableUsername($user->name);
        });

        // Deleting an account removes every file it uploaded, not just its database rows.
        static::deleting(function (User $user) {
            $user->goals()->get()->each->delete();
            $user->highlights()->get()->each->delete();
            Storage::disk(self::AVATAR_DISK)->delete(array_filter([$user->avatar_path, $user->cover_path]));
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'onboarded_at' => 'datetime',
            'kickoff_dismissed_at' => 'datetime',
            'password' => 'hashed',
            'profile_visibility' => ProfileVisibility::class,
            'discoverable' => 'boolean',
            'notification_settings' => 'array',
        ];
    }

    /**
     * What this person wants emailed, and when their daily reminders arrive.
     */
    public function notificationSettings(): NotificationSettings
    {
        return NotificationSettings::from($this->notification_settings);
    }

    /**
     * The link a calendar app subscribes to, made the first time it's needed.
     */
    public function calendarUrl(): string
    {
        if (! $this->calendar_token) {
            $this->resetCalendarToken();
        }

        return route('calendar.feed', $this->calendar_token);
    }

    /**
     * Make a new calendar link, so the old one stops working.
     */
    public function resetCalendarToken(): void
    {
        $this->forceFill(['calendar_token' => Str::random(40)])->save();
    }

    /**
     * Whether this is a one-click demo account (a visitor or one of their pretend friends).
     */
    public function isDemo(): bool
    {
        return $this->demo_sandbox !== null;
    }

    /**
     * Whether two people live in the same world: both real members, or both in the same demo. Demo accounts never
     * reach real members, and real members never see demo ones.
     */
    public function sharesWorldWith(User $other): bool
    {
        return $this->demo_sandbox === $other->demo_sandbox;
    }

    /**
     * Whether this person runs GoalLine: their email is in the ADMIN_EMAILS setting.
     */
    public function isAdmin(): bool
    {
        return $this->hasVerifiedEmail() && in_array(strtolower($this->email), array_map('strtolower', config('goalline.admin_emails')), true);
    }

    /**
     * Challenges this person has joined.
     *
     * @return BelongsToMany<Challenge, $this>
     */
    public function challenges(): BelongsToMany
    {
        return $this->belongsToMany(Challenge::class, 'challenge_members')->withTimestamps();
    }

    /**
     * Devices where this person turned on push notifications.
     *
     * @return HasMany<PushSubscription, $this>
     */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /**
     * Badges earned and GoalLeague awards won.
     *
     * @return HasMany<UserBadge, $this>
     */
    public function badges(): HasMany
    {
        return $this->hasMany(UserBadge::class)->latest('earned_at');
    }

    /**
     * The goals this person is pursuing.
     *
     * @return HasMany<Goal, $this>
     */
    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    /**
     * The leagues this person plays in.
     *
     * @return BelongsToMany<League, $this>
     */
    public function leagues(): BelongsToMany
    {
        return $this->belongsToMany(League::class, 'league_members')->withPivot('goal_id')->withTimestamps();
    }

    /**
     * Settled league results, for the overall W–T–L record.
     *
     * @return HasMany<LeagueResult, $this>
     */
    public function leagueResults(): HasMany
    {
        return $this->hasMany(LeagueResult::class);
    }

    /**
     * Up to two initials, for when there's no profile photo.
     */
    public function initials(): string
    {
        return Str::of($this->name)->explode(' ')->filter()->take(2)
            ->map(fn (string $part) => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');
    }

    /**
     * Where the profile photo is served from, if there is one.
     */
    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? route('avatars.show', ['user' => $this, 'v' => substr(md5($this->avatar_path), 0, 8)]) : null;
    }

    /**
     * Replace the profile photo, removing the old file.
     */
    public function replaceAvatar(?string $path): void
    {
        if ($this->avatar_path) {
            Storage::disk(self::AVATAR_DISK)->delete($this->avatar_path);
        }

        $this->forceFill(['avatar_path' => $path])->save();
    }

    /**
     * A free handle based on the person's name, like "jordan.rivera" or "jordan.rivera4821".
     */
    public static function availableUsername(string $name): string
    {
        $base = Str::of(Str::ascii($name))->lower()->replaceMatches('/[^a-z0-9]+/', '.')->trim('.')->limit(20, '')->toString() ?: 'member';
        $username = $base;

        while (self::where('username', $username)->exists()) {
            $username = $base.random_int(1000, 9999);
        }

        return $username;
    }

    /**
     * The person's time zone, or the app's when they haven't picked one.
     */
    public function timezoneName(): string
    {
        return $this->timezone ?: config('app.timezone');
    }

    /**
     * The goal pinned to the top of the profile.
     *
     * @return BelongsTo<Goal, $this>
     */
    public function featuredGoal(): BelongsTo
    {
        return $this->belongsTo(Goal::class, 'featured_goal_id');
    }

    /**
     * Where the cover photo is served from, if there is one.
     */
    public function coverUrl(): ?string
    {
        return $this->cover_path ? route('covers.show', ['user' => $this, 'v' => substr(md5($this->cover_path), 0, 8)]) : null;
    }

    /**
     * Replace the cover photo, removing the old file.
     */
    public function replaceCover(?string $path): void
    {
        if ($this->cover_path) {
            Storage::disk(self::AVATAR_DISK)->delete($this->cover_path);
        }

        $this->forceFill(['cover_path' => $path])->save();
    }

    /**
     * Where this person's profile lives.
     */
    public function profileUrl(): string
    {
        return route('profiles.show', $this);
    }

    /**
     * Whether this person and another play in at least one league together.
     */
    public function sharesLeagueWith(User $other): bool
    {
        return $this->leagues()->whereHas('members', fn ($members) => $members->where('user_id', $other->id))->exists();
    }

    /**
     * Use the username in profile links: /u/jordan.rivera.
     */
    public function getRouteKeyName(): string
    {
        return 'username';
    }

    /**
     * Chat messages from others, across all of this person's leagues, posted since they last looked.
     */
    public function unreadLeagueMessages(): int
    {
        return LeagueMessage::query()
            ->join('league_members', 'league_members.league_id', '=', 'league_messages.league_id')
            ->where('league_members.user_id', $this->id)
            ->whereRaw('league_messages.id > COALESCE(league_members.last_read_message_id, 0)')
            ->where(fn ($query) => $query->whereNull('league_messages.user_id')->orWhere('league_messages.user_id', '!=', $this->id))
            ->count();
    }

    /**
     * Friend requests and friendships this person is part of, either side.
     *
     * @return Builder<Friendship>
     */
    public function friendships(): Builder
    {
        return Friendship::query()->where(fn ($query) => $query->where('requester_id', $this->id)->orWhere('addressee_id', $this->id));
    }

    /**
     * IDs of this person's accepted friends.
     *
     * @return Collection<int, int>
     */
    public function friendIds(): Collection
    {
        return $this->friendships()
            ->where('status', Friendship::ACCEPTED)
            ->get(['requester_id', 'addressee_id'])
            ->toBase()
            ->map(fn (Friendship $friendship) => $friendship->requester_id === $this->id ? $friendship->addressee_id : $friendship->requester_id)
            ->values();
    }

    /**
     * Whether two people are friends.
     */
    public function isFriendsWith(User $other): bool
    {
        return ! $this->is($other) && Friendship::between($this, $other)->where('status', Friendship::ACCEPTED)->exists();
    }

    /**
     * The friendship or pending request between this person and another, if any.
     */
    public function friendshipWith(User $other): ?Friendship
    {
        return Friendship::between($this, $other)->first();
    }

    /**
     * Whether this person can nudge another: friends and GoalLeague mates.
     */
    public function canNudge(User $other): bool
    {
        return ! $this->is($other) && ($this->isFriendsWith($other) || $this->sharesLeagueWith($other));
    }

    /**
     * Whether this person already nudged another recently. One nudge a day keeps it friendly.
     */
    public function recentlyNudged(User $other): bool
    {
        return DB::table('nudges')
            ->where('sender_id', $this->id)
            ->where('recipient_id', $other->id)
            ->where('created_at', '>=', now()->subHours(self::NUDGE_COOLDOWN_HOURS))
            ->exists();
    }

    /**
     * This person's friends, by name.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public function friends(): \Illuminate\Database\Eloquent\Collection
    {
        return User::whereIn('id', $this->friendIds())->orderBy('name')->get();
    }

    /**
     * Everyone this person shares a GoalLeague with, not counting themselves.
     *
     * @return Collection<int, int>
     */
    public function leagueMateIds(): Collection
    {
        return LeagueMember::whereIn('league_id', $this->leagues()->select('leagues.id'))
            ->where('user_id', '!=', $this->id)
            ->distinct()
            ->pluck('user_id');
    }

    /**
     * Highlights this person shared, newest first.
     *
     * @return HasMany<Highlight, $this>
     */
    public function highlights(): HasMany
    {
        return $this->hasMany(Highlight::class)->latest('id');
    }
}
