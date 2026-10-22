<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'body'])]
class HighlightComment extends Model
{
    public const MAX_LENGTH = 500;

    /**
     * @return BelongsTo<Highlight, $this>
     */
    public function highlight(): BelongsTo
    {
        return $this->belongsTo(Highlight::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
