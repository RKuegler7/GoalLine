<?php

namespace App\Models;

use App\GoalCategory;
use App\GoalStatus;
use Database\Factories\GoalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['title', 'category', 'why', 'target_date'])]
class Goal extends Model
{
    /** @use HasFactory<GoalFactory> */
    use HasFactory;

    /**
     * Default values for new goals.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => GoalCategory::class,
            'status' => GoalStatus::class,
            'target_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * The person pursuing this goal.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Limit the query to goals still being pursued.
     *
     * @param  Builder<Goal>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', GoalStatus::Active);
    }

    /**
     * Whole days from today until the target date. Negative once the date has passed.
     */
    public function daysRemaining(): int
    {
        return (int) today()->diffInDays($this->target_date, false);
    }
}
