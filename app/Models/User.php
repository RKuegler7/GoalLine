<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'username', 'bio', 'location'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Profile photos are kept on the private disk and served through a route.
     */
    public const AVATAR_DISK = 'local';

    /**
     * Give every new member a handle they can change later.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->username ??= self::availableUsername($user->name);
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
            'password' => 'hashed',
        ];
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
}
