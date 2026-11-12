<?php

namespace App\Models;

use App\MedalType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A medal code awarded from settled results. See MedalAwarder and config/medals.php.
 */
#[Fillable(['user_id', 'type', 'tier', 'league_id', 'goal_id', 'season', 'award_key', 'metadata', 'awarded_at'])]
class Medal extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MedalType::class,
            'metadata' => 'array',
            'awarded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<League, $this>
     */
    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    /**
     * @return BelongsTo<Goal, $this>
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    /**
     * The medal's name with its tier: "Streak · 8 weeks", "Podium · 2nd", "Goal Achieved · Verified".
     */
    public function title(): string
    {
        $detail = $this->metadata['tier_label'] ?? null;

        return $this->type->label().($detail ? ' · '.$detail : '');
    }

    /**
     * One line on why it was awarded.
     */
    public function detail(): string
    {
        return (string) ($this->metadata['detail'] ?? $this->type->rule());
    }

    public function symbol(): string
    {
        return match (true) {
            $this->type === MedalType::Podium && $this->tier === 'silver' => '🥈',
            $this->type === MedalType::Podium && $this->tier === 'bronze' => '🥉',
            default => $this->type->symbol(),
        };
    }

    /**
     * Lower is more special: used to pick someone's best medal.
     */
    public function rank(): int
    {
        $order = array_flip(config('medals.order'));

        return ($order[$this->type->value] ?? 99) * 10 + match ($this->tier) {
            'gold', 'verified', 'many' => 0,
            'silver' => 1,
            'bronze' => 2,
            default => 3,
        };
    }
}
