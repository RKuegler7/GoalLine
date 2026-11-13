<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone a member muted: their posts never show for that member (Explore, Highlights, the Clubhouse feed).
 */
#[Fillable(['user_id', 'muted_user_id'])]
class UserMute extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function muted(): BelongsTo
    {
        return $this->belongsTo(User::class, 'muted_user_id');
    }
}
