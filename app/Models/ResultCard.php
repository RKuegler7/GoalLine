<?php

namespace App\Models;

use App\ShareLink;
use Database\Factories\ResultCardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A weekly result card: a frozen snapshot of one GoalWeek's result, computed by the scoring code when the card was
 * made. Its public link (/c/{token}) works only once the owner opts in, until they revoke it or turn public cards off.
 *
 * @property array{v: int, name: string, initials: string, weekLabel: string, goal: string, percent: int, band: string, bandLabel: string, needsProof: bool, linePoints: int, final: bool, match: array{outcome: string, label: string, points: int, photoFinish: bool, bye: bool}|null, weeklyPoints: int, onTheLine: int, league: string|null} $payload
 */
#[Fillable(['roadmap_week_id', 'payload'])]
class ResultCard extends Model
{
    /** @use HasFactory<ResultCardFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (ResultCard $card) {
            // 48 random characters: unguessable, and nothing in it says who or what.
            $card->token ??= Str::random(48);
            $card->content_hash ??= self::hashOf($card->payload);
        });
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'public_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * A stable hash of what the card shows, used to cache its images.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function hashOf(array $payload): string
    {
        return sha1(json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Whether the public link works right now: opted in, not revoked, and the owner still allows public cards.
     */
    public function isPublic(): bool
    {
        return $this->public_at !== null && $this->revoked_at === null && (bool) $this->user?->public_cards;
    }

    public function publicUrl(): string
    {
        return ShareLink::route('cards.public', $this->token);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<RoadmapWeek, $this>
     */
    public function week(): BelongsTo
    {
        return $this->belongsTo(RoadmapWeek::class, 'roadmap_week_id');
    }
}
