<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A friend request, and once accepted, a friendship. Friends see each other's "League + friends" posts.
 */
#[Fillable(['requester_id', 'addressee_id'])]
class Friendship extends Model
{
    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function addressee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'addressee_id');
    }

    /**
     * The friendship or request between two people, whoever sent it.
     *
     * @param  Builder<Friendship>  $query
     */
    public function scopeBetween(Builder $query, User $one, User $other): void
    {
        $query->where(fn ($either) => $either
            ->where(fn ($pair) => $pair->where('requester_id', $one->id)->where('addressee_id', $other->id))
            ->orWhere(fn ($pair) => $pair->where('requester_id', $other->id)->where('addressee_id', $one->id)));
    }

    public function isAccepted(): bool
    {
        return $this->status === self::ACCEPTED;
    }

    /**
     * The other person in the friendship.
     */
    public function otherThan(User $user): User
    {
        return $this->requester_id === $user->id ? $this->addressee : $this->requester;
    }
}
