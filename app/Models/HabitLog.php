<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One day a habit was logged, with the number for a number or minutes habit. A day counts only when it met the
 * habit's rule at the time it was logged, so changing the rule later never rewrites past days.
 */
#[Fillable(['logged_on', 'value', 'met'])]
class HabitLog extends Model
{
    /**
     * A plain check-off counts.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'met' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'logged_on' => 'date',
            'value' => 'float',
            'met' => 'boolean',
        ];
    }

    /**
     * The habit that was done.
     *
     * @return BelongsTo<Habit, $this>
     */
    public function habit(): BelongsTo
    {
        return $this->belongsTo(Habit::class);
    }
}
