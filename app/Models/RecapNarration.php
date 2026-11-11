<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The saved summary of one member's finished week: written by the AI narrator from code-computed numbers, a saved
 * sample in the demo, or the plain template when the narrator couldn't give a valid one.
 */
class RecapNarration extends Model
{
    public const AI = 'ai';

    public const SAMPLE = 'sample';

    public const TEMPLATE = 'template';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['week_starts_on' => 'date'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isAi(): bool
    {
        return $this->source === self::AI;
    }
}
